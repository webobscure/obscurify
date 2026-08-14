<?php

namespace App\Domain\Cms\Http\Controllers;

use App\Domain\Cms\Application\CreatePage;
use App\Domain\Cms\Application\DuplicatePage;
use App\Domain\Cms\Application\PublishPageVersion;
use App\Domain\Cms\Application\RollbackPage;
use App\Domain\Cms\Http\Requests\StorePageRequest;
use App\Domain\Cms\Http\Requests\UpdatePageRequest;
use App\Domain\Cms\Http\Resources\PageResource;
use App\Domain\Cms\Http\Resources\PageVersionResource;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Models\PageVersion;
use App\Domain\Themes\Support\ThemeRenderer;
use App\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

final class PageController extends Controller
{
    private const array WITH = ['versions', 'activePointer'];

    public function index(): AnonymousResourceCollection
    {
        $pages = Page::query()->with(self::WITH)->orderByDesc('created_at')->get();

        return PageResource::collection($pages);
    }

    public function show(Page $page): PageResource
    {
        return new PageResource($page->load(self::WITH));
    }

    public function store(StorePageRequest $request, CreatePage $action): PageResource
    {
        $page = $action->handle($request->validated());

        return new PageResource($page->load(self::WITH));
    }

    public function update(UpdatePageRequest $request, Page $page): PageResource
    {
        $page->update($request->validated());

        return new PageResource($page->load(self::WITH));
    }

    /**
     * Publishes the page's current draft version — see
     * PublishPageVersion for the freeze/activate/new-draft sequence.
     */
    public function publish(Page $page, PublishPageVersion $action): JsonResponse
    {
        $draft = $page->versions()->where('status', 'draft')->latest('version_number')->first();

        if ($draft === null) {
            throw ValidationException::withMessages(['page' => 'This page has no draft version to publish.']);
        }

        $action->handle($draft);

        return (new PageResource($page->fresh(self::WITH)))->response();
    }

    public function duplicate(Page $page, DuplicatePage $action): JsonResponse
    {
        $copy = $action->handle($page);

        return (new PageResource($copy->load(self::WITH)))->response()->setStatusCode(201);
    }

    /**
     * Renders the page's current *draft* version through the exact same
     * ThemeRenderer the storefront uses, without touching ActivePageVersion.
     * Resolves section/block *types* against the store's active theme
     * (not the theme's own draft) — this previews the page, not an
     * unrelated in-progress theme edit.
     */
    public function preview(Page $page, ThemeRenderer $renderer, TenantContext $tenantContext): JsonResponse
    {
        $draft = $page->versions()->where('status', 'draft')->latest('version_number')->firstOrFail();

        $rendered = $renderer->renderCmsPage($tenantContext->storeId(), $draft->sections);

        return response()->json(['data' => $rendered]);
    }

    public function versions(Page $page): AnonymousResourceCollection
    {
        $versions = $page->versions()->orderByDesc('version_number')->get();

        return PageVersionResource::collection($versions);
    }

    public function rollback(PageVersion $pageVersion, RollbackPage $action): JsonResponse
    {
        $action->handle($pageVersion);

        $page = Page::query()->findOrFail($pageVersion->page_id);

        return (new PageResource($page->load(self::WITH)))->response();
    }
}
