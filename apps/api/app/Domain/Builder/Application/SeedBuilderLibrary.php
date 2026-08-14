<?php

namespace App\Domain\Builder\Application;

use App\Domain\Builder\Enums\BuilderPresetType;
use App\Domain\Builder\Models\BuilderPreset;
use App\Domain\Builder\Support\BuiltInLibrary;
use App\Domain\Themes\Models\ThemeBlock;
use App\Domain\Themes\Models\ThemeSection;
use App\Domain\Themes\Models\ThemeVersion;

/**
 * Registers the built-in Section/Block Library (BuiltInLibrary) as real
 * ThemeSection/ThemeBlock *type* rows on a theme version, plus one
 * BuilderPreset per type so the visual Builder's "add section"/"add
 * block" picker has something to insert with sensible defaults already
 * filled in. Idempotent (safe to call more than once — every insert is
 * `firstOrCreate` keyed by the same uniqueness the underlying tables
 * already enforce), since a theme version's draft can be re-seeded
 * without duplicating rows.
 *
 * ThemeBlock is section-scoped by design (Milestone 13's schema — a
 * block type belongs to exactly one ThemeSection via a required FK,
 * unchanged here since "do not redesign the rendering engine" applies
 * to the theme engine's own schema too, not just ThemeRenderer's code).
 * A block meant to be usable on every section (e.g. "Button") is
 * therefore registered once *per section*, not once globally — the
 * cross product is what makes every block available everywhere,
 * without ThemeRenderer or ThemeBlock ever needing a "global block"
 * concept it doesn't have.
 */
final class SeedBuilderLibrary
{
    public function handle(ThemeVersion $version): void
    {
        $blockDefs = BuiltInLibrary::blocks();

        foreach (BuiltInLibrary::sections() as $sectionDef) {
            $section = ThemeSection::query()->firstOrCreate(
                ['theme_version_id' => $version->id, 'handle' => $sectionDef['handle']],
                ['name' => $sectionDef['name'], 'schema' => $sectionDef['schema']],
            );

            $this->seedPreset(BuilderPresetType::Section, $sectionDef['handle'], $sectionDef['name'], $sectionDef['schema']);

            foreach ($blockDefs as $blockDef) {
                ThemeBlock::query()->firstOrCreate(
                    ['theme_section_id' => $section->id, 'handle' => $blockDef['handle']],
                    ['theme_version_id' => $version->id, 'name' => $blockDef['name'], 'schema' => $blockDef['schema']],
                );
            }
        }

        foreach ($blockDefs as $blockDef) {
            $this->seedPreset(BuilderPresetType::Block, $blockDef['handle'], $blockDef['name'], $blockDef['schema']);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $schema
     */
    private function seedPreset(BuilderPresetType $type, string $handle, string $name, array $schema): void
    {
        $defaults = [];
        foreach ($schema as $field) {
            if (isset($field['id'])) {
                $defaults[$field['id']] = $field['default'] ?? null;
            }
        }

        BuilderPreset::query()->firstOrCreate(
            ['type' => $type->value, 'handle' => $handle, 'name' => $name],
            ['settings' => $defaults],
        );
    }
}
