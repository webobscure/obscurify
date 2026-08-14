<?php

namespace App\Domain\Storefront\Http\Controllers;

use App\Domain\Cms\Enums\SeoSubjectType;
use App\Domain\Cms\Models\ActivePageVersion;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Models\SeoMetadata;
use App\Domain\Themes\Support\ThemeRenderer;
use App\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * The one endpoint the storefront calls to render a CMS page — mirrors
 * StorefrontThemeController exactly: no auth, tenant resolved from the
 * Host header by `storefront.tenant`, so there is no session that could
 * ever request a draft. A page's draft-preview equivalent is the
 * admin-only `PageController::preview`.
 *
 * Deliberately does not filter on `Page.status` — publishing a
 * PageVersion never touches that field (same as Theme.status, which
 * `StorefrontThemeController` also ignores). Whether a page is live is
 * entirely decided by ActivePageVersion existing, not by this label.
 */
final class StorefrontPageController extends Controller
{
    public function show(string $slug, ThemeRenderer $renderer, TenantContext $tenantContext): JsonResponse
    {
        $page = Page::query()->where('slug', $slug)->firstOrFail();
        $active = ActivePageVersion::query()->where('page_id', $page->id)->firstOrFail();

        $rendered = $renderer->renderCmsPage($tenantContext->storeId(), $active->pageVersion->sections);

        $seo = SeoMetadata::query()
            ->where('subject_type', SeoSubjectType::PageVersion->value)
            ->where('subject_id', $active->page_version_id)
            ->first();

        return response()->json(['data' => [
            'page' => ['id' => $page->id, 'title' => $page->title, 'slug' => $page->slug],
            'rendered' => $rendered,
            'seo' => $seo === null ? null : [
                'meta_title' => $seo->meta_title,
                'meta_description' => $seo->meta_description,
                'canonical_url' => $seo->canonical_url,
                'og_image' => $seo->og_image,
            ],
        ]]);
    }
}
