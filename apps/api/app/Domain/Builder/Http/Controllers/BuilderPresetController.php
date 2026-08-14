<?php

namespace App\Domain\Builder\Http\Controllers;

use App\Domain\Builder\Application\SeedBuilderLibrary;
use App\Domain\Builder\Http\Resources\BuilderPresetResource;
use App\Domain\Builder\Models\BuilderPreset;
use App\Domain\Themes\Enums\ThemeVersionStatus;
use App\Domain\Themes\Models\ActiveTheme;
use App\Domain\Themes\Models\ThemeVersion;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The Section Library / Block Library picker (spec section 13:
 * `GET /builder/presets`). Ensures the active theme's current draft
 * version actually has the built-in section/block *types* registered
 * before listing presets for them — a preset is useless if the type it
 * targets doesn't exist yet on the version a merchant is currently
 * editing (e.g. right after a theme was newly created, or right after a
 * publish opened a fresh draft) — SeedBuilderLibrary is idempotent, so
 * this is cheap to call on every request rather than needing a separate
 * setup step.
 */
final class BuilderPresetController extends Controller
{
    public function index(Request $request, SeedBuilderLibrary $seedBuilderLibrary): AnonymousResourceCollection
    {
        $active = ActiveTheme::query()->first();

        if ($active !== null) {
            $draft = ThemeVersion::query()
                ->where('theme_id', $active->theme_id)
                ->where('status', ThemeVersionStatus::Draft->value)
                ->latest('version_number')
                ->first();

            if ($draft !== null) {
                $seedBuilderLibrary->handle($draft);
            }
        }

        $query = BuilderPreset::query()->orderBy('type')->orderBy('name');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        return BuilderPresetResource::collection($query->get());
    }
}
