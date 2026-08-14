<?php

namespace App\Domain\Themes\Support;

use App\Domain\Themes\Enums\ThemeTemplateType;
use App\Domain\Themes\Enums\ThemeVersionStatus;
use App\Domain\Themes\Exceptions\ThemeNotActiveException;
use App\Domain\Themes\Models\ActiveTheme;
use App\Domain\Themes\Models\ThemeBlock;
use App\Domain\Themes\Models\ThemeSection;
use App\Domain\Themes\Models\ThemeTemplate;
use App\Domain\Themes\Models\ThemeVersion;
use Illuminate\Support\Collection;

/**
 * The engine every storefront page renders through (spec section 9) —
 * Commerce Core (Orders/Products/Checkout/...) never references this
 * class or anything in `App\Domain\Themes`; only Storefront controllers
 * call it. Resolves which ThemeVersion applies (live vs. preview),
 * loads the requested template, and merges each section instance's
 * settings against its type's schema defaults — the one place that
 * merge happens, so the storefront frontend only ever receives fully
 * resolved values, never a schema to interpret itself.
 */
final class ThemeRenderer
{
    public function render(string $storeId, ThemeTemplateType $type, bool $preview = false, ?string $previewVersionId = null): RenderedPage
    {
        $version = $this->resolveVersion($preview, $previewVersionId);

        $template = ThemeTemplate::query()
            ->where('theme_version_id', $version->id)
            ->where('type', $type->value)
            ->first();

        return $this->renderVersion($version, $type->value, $template->sections ?? [], $preview);
    }

    /**
     * Renders an arbitrary raw section-instance array (e.g. a CMS
     * PageVersion's own `sections` column) against the store's current
     * theme — the same section/block type resolution `render()` uses for
     * a built-in template slot, just fed different instance data. Lets
     * `App\Domain\Cms` reuse this engine without `ThemeRenderer` ever
     * needing to know anything about Cms models (Cms depends on Themes,
     * never the reverse — see docs/architecture/cms.md).
     *
     * @param  array<int, array<string, mixed>>  $rawSections
     */
    public function renderCmsPage(string $storeId, array $rawSections, bool $preview = false, ?string $previewVersionId = null): RenderedPage
    {
        $version = $this->resolveVersion($preview, $previewVersionId);

        return $this->renderVersion($version, ThemeTemplateType::Page->value, $rawSections, $preview);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawSections
     */
    private function renderVersion(ThemeVersion $version, string $template, array $rawSections, bool $preview): RenderedPage
    {
        $sectionsByHandle = $version->sections()->get()->keyBy('handle');
        $blocksBySection = $version->blocks()->get()->collect()->groupBy('theme_section_id');
        $globalSettings = $version->settings()->get()->pluck('value', 'key')->all();

        $sections = collect($rawSections)
            ->map(fn (array $instance) => $this->resolveSection($instance, $sectionsByHandle, $blocksBySection))
            ->filter()
            ->values()
            ->all();

        return new RenderedPage(
            template: $template,
            sections: $sections,
            globalSettings: $globalSettings,
            themeId: $version->theme_id,
            themeVersionId: $version->id,
            isPreview: $preview,
        );
    }

    /**
     * @param  array<string, mixed>  $instance
     * @param  Collection<string, ThemeSection>  $sectionsByHandle
     * @param  Collection<string, Collection<int, ThemeBlock>>  $blocksBySection
     */
    private function resolveSection(array $instance, Collection $sectionsByHandle, Collection $blocksBySection): ?RenderedSection
    {
        $sectionType = $sectionsByHandle->get($instance['section_handle'] ?? null);

        if ($sectionType === null) {
            return null;
        }

        $settings = array_merge($sectionType->defaultSettings(), $instance['settings'] ?? []);
        $blockDefsByHandle = ($blocksBySection->get($sectionType->id) ?? collect())->keyBy('handle');

        $blocks = collect($instance['blocks'] ?? [])
            ->map(fn (array $blockInstance) => $this->resolveBlock($blockInstance, $blockDefsByHandle))
            ->filter()
            ->values()
            ->all();

        return new RenderedSection(
            id: $instance['id'] ?? null,
            handle: $sectionType->handle,
            name: $sectionType->name,
            settings: $settings,
            blocks: $blocks,
        );
    }

    /**
     * A block may itself carry nested child blocks (spec Milestone 15
     * section 2: "Nested blocks" — e.g. items inside an Accordion/Tabs
     * block) — resolved the exact same way, recursively, against the
     * same per-section block-type registry every block on that section
     * shares (a nested block is still a block *of that section*, not a
     * separate concept). Non-nested content (every block before this
     * milestone) is unaffected: an instance with no `blocks` key simply
     * resolves to `'blocks' => []` here, same as it always implicitly
     * did.
     *
     * @param  array<string, mixed>  $blockInstance
     * @param  Collection<string, ThemeBlock>  $blockDefsByHandle
     * @return array{id: string|null, handle: string, settings: array<string, mixed>, blocks: array<int, mixed>}|null
     */
    private function resolveBlock(array $blockInstance, Collection $blockDefsByHandle): ?array
    {
        $blockType = $blockDefsByHandle->get($blockInstance['block_handle'] ?? null);

        if ($blockType === null) {
            return null;
        }

        $defaults = [];
        foreach ($blockType->schema as $field) {
            if (isset($field['id'])) {
                $defaults[$field['id']] = $field['default'] ?? null;
            }
        }

        $children = collect($blockInstance['blocks'] ?? [])
            ->map(fn (array $child) => $this->resolveBlock($child, $blockDefsByHandle))
            ->filter()
            ->values()
            ->all();

        return [
            'id' => $blockInstance['id'] ?? null,
            'handle' => $blockType->handle,
            'settings' => array_merge($defaults, $blockInstance['settings'] ?? []),
            'blocks' => $children,
        ];
    }

    private function resolveVersion(bool $preview, ?string $previewVersionId): ThemeVersion
    {
        if ($preview && $previewVersionId !== null) {
            return ThemeVersion::query()->findOrFail($previewVersionId);
        }

        $active = ActiveTheme::query()->first();

        if ($active === null) {
            throw ThemeNotActiveException::make();
        }

        if ($preview) {
            return ThemeVersion::query()
                ->where('theme_id', $active->theme_id)
                ->where('status', ThemeVersionStatus::Draft->value)
                ->latest('version_number')
                ->first() ?? ThemeVersion::query()->findOrFail($active->theme_version_id);
        }

        return ThemeVersion::query()->findOrFail($active->theme_version_id);
    }
}
