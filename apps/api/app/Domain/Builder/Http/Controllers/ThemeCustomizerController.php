<?php

namespace App\Domain\Builder\Http\Controllers;

use App\Domain\Builder\Support\ThemeCustomizerSchema;
use App\Domain\Themes\Enums\ThemeVersionStatus;
use App\Domain\Themes\Exceptions\ThemeNotActiveException;
use App\Domain\Themes\Models\ActiveTheme;
use App\Domain\Themes\Models\ThemeVersion;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * A convenience read endpoint for the visual Theme Customizer — resolves
 * the active theme's *current draft* version (customizing, like every
 * other Builder surface, only ever edits the draft; visitors never see
 * unpublished changes) so the admin UI doesn't need to chain a
 * `GET /themes` call first just to find a version id. Saving reuses the
 * existing `PATCH /theme-versions/{id}/settings` endpoint directly —
 * this schema is purely field metadata for the picker, not a new write
 * path (ThemeSettingController already validates/persists).
 */
final class ThemeCustomizerController extends Controller
{
    public function show(): JsonResponse
    {
        $active = ActiveTheme::query()->first();

        if ($active === null) {
            throw ThemeNotActiveException::make();
        }

        $draft = ThemeVersion::query()
            ->where('theme_id', $active->theme_id)
            ->where('status', ThemeVersionStatus::Draft->value)
            ->latest('version_number')
            ->firstOrFail();

        return response()->json(['data' => [
            'theme_version_id' => $draft->id,
            'schema' => ThemeCustomizerSchema::fields(),
            'values' => $draft->settings()->get()->pluck('value', 'key'),
        ]]);
    }
}
