<?php

namespace App\Domain\Themes\Application;

use App\Domain\Themes\Enums\ThemeStatus;
use App\Domain\Themes\Enums\ThemeVersionStatus;
use App\Domain\Themes\Models\Theme;
use App\Domain\Themes\Models\ThemeVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Clones an entire theme (spec section 3/13) — a new Theme row plus a
 * single new draft ThemeVersion carrying a full copy of the source
 * theme's *current* content (its draft version if one exists, otherwise
 * its latest version) — so a merchant can experiment freely without
 * risking the original.
 */
final class DuplicateTheme
{
    public function __construct(private readonly CloneThemeVersionContent $cloneThemeVersionContent) {}

    public function handle(Theme $source): Theme
    {
        return DB::transaction(function () use ($source) {
            $sourceVersion = $source->versions()
                ->where('status', ThemeVersionStatus::Draft->value)
                ->latest('version_number')
                ->first() ?? $source->versions()->latest('version_number')->firstOrFail();

            $copy = Theme::query()->create([
                'name' => $source->name.' (copy)',
                'slug' => $source->slug.'-copy-'.Str::lower(Str::random(6)),
                'status' => ThemeStatus::Draft->value,
            ]);

            $newVersion = ThemeVersion::query()->create([
                'theme_id' => $copy->id,
                'version_number' => 1,
                'status' => ThemeVersionStatus::Draft->value,
            ]);

            $this->cloneThemeVersionContent->handle($sourceVersion, $newVersion);

            return $copy->load('versions');
        });
    }
}
