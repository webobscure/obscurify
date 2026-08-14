<?php

namespace App\Domain\Builder\Support;

use App\Domain\Builder\Models\BlockInstance;
use App\Domain\Builder\Models\PageLayout;
use App\Domain\Builder\Models\SectionInstance;

/**
 * The reverse of SerializeSectionInstances — rebuilds a PageLayout's
 * SectionInstance/BlockInstance rows from a raw sections array (the
 * same shape PageVersion.sections stores), replacing them wholesale.
 * Mirrors the "replace children in full" pattern ThemeTemplateController
 * and PageVersionController already use for a jsonb-backed ordered
 * collection — deleting and recreating is simpler and safer than
 * diffing drag-and-drop reorders against existing rows, and a page's
 * section/block count is always small enough that this is cheap.
 */
final class ReplaceSectionInstancesFromArray
{
    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    public function handle(PageLayout $layout, array $sections): void
    {
        $layout->sectionInstances()->delete();

        foreach (array_values($sections) as $position => $sectionData) {
            $section = SectionInstance::query()->create([
                'page_layout_id' => $layout->id,
                'section_handle' => $sectionData['section_handle'] ?? '',
                'position' => $position,
                'settings' => $sectionData['settings'] ?? [],
            ]);

            $this->createBlocks($section->id, null, $sectionData['blocks'] ?? []);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function createBlocks(string $sectionInstanceId, ?string $parentBlockInstanceId, array $blocks): void
    {
        foreach (array_values($blocks) as $position => $blockData) {
            $block = BlockInstance::query()->create([
                'section_instance_id' => $sectionInstanceId,
                'parent_block_instance_id' => $parentBlockInstanceId,
                'block_handle' => $blockData['block_handle'] ?? '',
                'position' => $position,
                'settings' => $blockData['settings'] ?? [],
            ]);

            $this->createBlocks($sectionInstanceId, $block->id, $blockData['blocks'] ?? []);
        }
    }
}
