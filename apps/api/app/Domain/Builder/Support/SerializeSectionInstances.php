<?php

namespace App\Domain\Builder\Support;

use App\Domain\Builder\Models\BlockInstance;
use App\Domain\Builder\Models\PageLayout;
use App\Domain\Builder\Models\SectionInstance;
use Illuminate\Support\Collection;

/**
 * Converts a PageLayout's relational SectionInstance/BlockInstance rows
 * into the exact jsonb array shape PageVersion.sections (and
 * ThemeTemplate.sections) already use —
 * `[{id, section_handle, settings, blocks: [{id, block_handle, settings,
 * blocks: [...]}]}]`, blocks nesting recursively. This is the one place
 * the relational-to-jsonb direction happens; ThemeRenderer never reads
 * SectionInstance/BlockInstance directly, only this array, so it stays
 * completely unaware Builder exists.
 */
final class SerializeSectionInstances
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(PageLayout $layout): array
    {
        return $layout->sectionInstances()->with('topLevelBlocks.children')->get()
            ->map(fn (SectionInstance $section) => [
                'id' => $section->id,
                'section_handle' => $section->section_handle,
                'settings' => $section->settings,
                'blocks' => $this->serializeBlocks($section->topLevelBlocks),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, BlockInstance>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function serializeBlocks(Collection $blocks): array
    {
        return $blocks->map(fn ($block) => [
            'id' => $block->id,
            'block_handle' => $block->block_handle,
            'settings' => $block->settings,
            'blocks' => $this->serializeBlocks($block->children),
        ])->all();
    }
}
