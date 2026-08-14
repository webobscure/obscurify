<?php

namespace App\Domain\Themes\Http\Controllers;

use App\Domain\Themes\Models\ThemeVersion;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ThemeTemplateController extends Controller
{
    public function index(ThemeVersion $themeVersion): JsonResponse
    {
        $templates = $themeVersion->templates()->get()->map(fn ($t) => [
            'id' => $t->id,
            'type' => $t->type->value,
            'name' => $t->name,
            'sections' => $t->sections,
        ]);

        return response()->json(['data' => $templates]);
    }

    /**
     * Replaces a template's ordered section-instance list wholesale
     * (spec section 5/6: sections and their blocks are reorderable) —
     * the same "replace children in full" pattern ShippingZoneRegion
     * established, appropriate here since the whole array is one jsonb
     * column, not child rows.
     */
    public function update(Request $request, ThemeVersion $themeVersion, string $type): JsonResponse
    {
        $themeVersion->assertEditable();

        $data = $request->validate(['sections' => ['present', 'array']]);

        $template = $themeVersion->templates()->where('type', $type)->firstOrFail();
        $template->update(['sections' => $data['sections']]);

        return response()->json(['data' => [
            'id' => $template->id,
            'type' => $template->type->value,
            'name' => $template->name,
            'sections' => $template->sections,
        ]]);
    }
}
