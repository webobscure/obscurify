<?php

namespace App\Domain\Themes\Application;

use App\Domain\Themes\Enums\ThemeStatus;
use App\Domain\Themes\Enums\ThemeTemplateType;
use App\Domain\Themes\Enums\ThemeVersionStatus;
use App\Domain\Themes\Models\Theme;
use App\Domain\Themes\Models\ThemeSection;
use App\Domain\Themes\Models\ThemeTemplate;
use App\Domain\Themes\Models\ThemeVersion;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Theme with its first draft ThemeVersion (v1), seeded with an
 * empty template for every fixed slot (spec section 4) and one starter
 * section type ("hero") so a freshly created theme is immediately
 * renderable rather than empty. Never activates it — see ActivateTheme.
 */
final class CreateTheme
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Theme
    {
        return DB::transaction(function () use ($data) {
            $theme = Theme::query()->create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'status' => ThemeStatus::Draft->value,
            ]);

            $version = ThemeVersion::query()->create([
                'theme_id' => $theme->id,
                'version_number' => 1,
                'status' => ThemeVersionStatus::Draft->value,
            ]);

            $hero = ThemeSection::query()->create([
                'theme_version_id' => $version->id,
                'handle' => 'hero',
                'name' => 'Hero',
                'schema' => [
                    ['id' => 'heading', 'type' => 'text', 'label' => 'Heading', 'default' => 'Welcome to our store'],
                    ['id' => 'subheading', 'type' => 'text', 'label' => 'Subheading', 'default' => ''],
                    ['id' => 'image', 'type' => 'image', 'label' => 'Background image', 'default' => null],
                ],
                'presets' => ['default' => ['heading' => 'Welcome to our store', 'subheading' => '', 'image' => null]],
            ]);

            foreach (ThemeTemplateType::cases() as $type) {
                ThemeTemplate::query()->create([
                    'theme_version_id' => $version->id,
                    'type' => $type->value,
                    'name' => $type->value,
                    'sections' => $type === ThemeTemplateType::Home
                        ? [['id' => 'hero-1', 'section_handle' => $hero->handle, 'settings' => [], 'blocks' => []]]
                        : [],
                ]);
            }

            return $theme->load('versions');
        });
    }
}
