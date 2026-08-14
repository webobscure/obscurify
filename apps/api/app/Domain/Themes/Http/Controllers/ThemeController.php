<?php

namespace App\Domain\Themes\Http\Controllers;

use App\Domain\Themes\Application\CreateTheme;
use App\Domain\Themes\Application\DuplicateTheme;
use App\Domain\Themes\Application\PublishThemeVersion;
use App\Domain\Themes\Application\RollbackTheme;
use App\Domain\Themes\Enums\ThemeTemplateType;
use App\Domain\Themes\Enums\ThemeVersionStatus;
use App\Domain\Themes\Http\Requests\StoreThemeRequest;
use App\Domain\Themes\Http\Requests\UpdateThemeRequest;
use App\Domain\Themes\Http\Resources\ThemeResource;
use App\Domain\Themes\Http\Resources\ThemeVersionResource;
use App\Domain\Themes\Models\Theme;
use App\Domain\Themes\Models\ThemeVersion;
use App\Domain\Themes\Support\ThemeRenderer;
use App\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

final class ThemeController extends Controller
{
    private const array WITH = ['versions', 'activePointer'];

    public function index(): AnonymousResourceCollection
    {
        $themes = Theme::query()->with(self::WITH)->orderByDesc('created_at')->get();

        return ThemeResource::collection($themes);
    }

    public function show(Theme $theme): ThemeResource
    {
        return new ThemeResource($theme->load(self::WITH));
    }

    public function store(StoreThemeRequest $request, CreateTheme $action): ThemeResource
    {
        $theme = $action->handle($request->validated());

        return new ThemeResource($theme->load(self::WITH));
    }

    public function update(UpdateThemeRequest $request, Theme $theme): ThemeResource
    {
        $theme->update($request->validated());

        return new ThemeResource($theme->load(self::WITH));
    }

    /**
     * Publishes the theme's current draft version — see
     * PublishThemeVersion for the freeze/activate/new-draft sequence.
     */
    public function publish(Theme $theme, PublishThemeVersion $action): JsonResponse
    {
        $draft = $theme->versions()->where('status', ThemeVersionStatus::Draft->value)->latest('version_number')->first();

        if ($draft === null) {
            throw ValidationException::withMessages(['theme' => 'This theme has no draft version to publish.']);
        }

        $action->handle($draft);

        return (new ThemeResource($theme->fresh(self::WITH)))->response();
    }

    public function duplicate(Theme $theme, DuplicateTheme $action): JsonResponse
    {
        $copy = $action->handle($theme);

        return (new ThemeResource($copy->load(self::WITH)))->response()->setStatusCode(201);
    }

    /**
     * Renders the theme's current *draft* version — spec section 11:
     * "Merchant can preview an unpublished theme. Visitors always see
     * the active theme." Never touches ActiveTheme.
     */
    public function preview(Request $request, Theme $theme, ThemeRenderer $renderer, TenantContext $tenantContext): JsonResponse
    {
        $draft = $theme->versions()->where('status', ThemeVersionStatus::Draft->value)->latest('version_number')->firstOrFail();
        $type = ThemeTemplateType::from($request->query('template', ThemeTemplateType::Home->value));

        $page = $renderer->render($tenantContext->storeId(), $type, preview: true, previewVersionId: $draft->id);

        return response()->json(['data' => $page]);
    }

    public function versions(Theme $theme): AnonymousResourceCollection
    {
        $versions = $theme->versions()->orderByDesc('version_number')->get();

        return ThemeVersionResource::collection($versions);
    }

    public function rollback(ThemeVersion $themeVersion, RollbackTheme $action): JsonResponse
    {
        $action->handle($themeVersion);

        $theme = Theme::query()->findOrFail($themeVersion->theme_id);

        return (new ThemeResource($theme->load(self::WITH)))->response();
    }
}
