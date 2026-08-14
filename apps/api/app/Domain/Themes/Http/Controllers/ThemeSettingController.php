<?php

namespace App\Domain\Themes\Http\Controllers;

use App\Domain\Themes\Models\ThemeSetting;
use App\Domain\Themes\Models\ThemeVersion;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global theme settings (spec section 7) for one ThemeVersion — refuses
 * to touch a published (immutable) version.
 */
final class ThemeSettingController extends Controller
{
    public function index(ThemeVersion $themeVersion): JsonResponse
    {
        return response()->json(['data' => $themeVersion->settings()->get()->pluck('value', 'key')]);
    }

    public function update(Request $request, ThemeVersion $themeVersion): JsonResponse
    {
        $themeVersion->assertEditable();

        $data = $request->validate(['settings' => ['required', 'array']]);

        foreach ($data['settings'] as $key => $value) {
            ThemeSetting::query()->updateOrCreate(
                ['theme_version_id' => $themeVersion->id, 'key' => $key],
                ['value' => $value],
            );
        }

        return response()->json(['data' => $themeVersion->settings()->get()->pluck('value', 'key')]);
    }
}
