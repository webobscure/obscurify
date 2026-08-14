<?php

namespace App\Domain\Themes\Application;

use App\Domain\Themes\Models\ThemeBlock;
use App\Domain\Themes\Models\ThemeSection;
use App\Domain\Themes\Models\ThemeSetting;
use App\Domain\Themes\Models\ThemeTemplate;
use App\Domain\Themes\Models\ThemeVersion;

/**
 * Copies every content row (sections, blocks, templates, settings) from
 * one ThemeVersion to another — the one place "duplicate this content"
 * logic lives, reused by both PublishThemeVersion (which always creates
 * a fresh draft cloned from the version it just published) and
 * DuplicateTheme (spec section 3/13's explicit "Duplicate" action).
 * Deliberately does not clone ThemeAsset rows — those are file-backed
 * (see ThemeAsset's own docblock) and are shared by reference across
 * versions of the same theme rather than physically copied on disk for
 * every draft.
 */
final class CloneThemeVersionContent
{
    public function handle(ThemeVersion $source, ThemeVersion $target): void
    {
        $sectionIdMap = [];

        foreach ($source->sections()->get() as $section) {
            $clone = ThemeSection::query()->create([
                'theme_version_id' => $target->id,
                'handle' => $section->handle,
                'name' => $section->name,
                'schema' => $section->schema,
                'presets' => $section->presets,
            ]);

            $sectionIdMap[$section->id] = $clone->id;
        }

        foreach ($source->blocks()->get() as $block) {
            ThemeBlock::query()->create([
                'theme_version_id' => $target->id,
                'theme_section_id' => $sectionIdMap[$block->theme_section_id] ?? $block->theme_section_id,
                'handle' => $block->handle,
                'name' => $block->name,
                'schema' => $block->schema,
            ]);
        }

        foreach ($source->templates()->get() as $template) {
            ThemeTemplate::query()->create([
                'theme_version_id' => $target->id,
                'type' => $template->type->value,
                'name' => $template->name,
                'sections' => $template->sections,
            ]);
        }

        foreach ($source->settings()->get() as $setting) {
            ThemeSetting::query()->create([
                'theme_version_id' => $target->id,
                'key' => $setting->key,
                'value' => $setting->value,
            ]);
        }
    }
}
