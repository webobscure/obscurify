<?php

namespace App\Domain\Storefront\Http\Controllers;

use App\Domain\Cms\Enums\BlogPostStatus;
use App\Domain\Cms\Models\ActivePageVersion;
use App\Domain\Cms\Models\BlogPost;
use App\Domain\Cms\Models\Page;
use App\Domain\Domains\Models\Domain;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * A minimal XML sitemap — live Pages and published BlogPosts only (spec:
 * SEO/sitemap). Products/Collections already have their own storefront
 * routes but are outside this milestone's scope to add here; this list
 * grows the same way ThemeTemplateType's slots do, as an additive
 * change, not a redesign. There is no single "the storefront's base
 * URL" the way there might be for a single-tenant app — each store
 * serves its storefront on its own custom domain (resolved per-request
 * by `storefront.tenant` from the Host header), so the sitemap must
 * read the requesting store's own primary Domain rather than a fixed
 * config value.
 */
final class StorefrontSitemapController extends Controller
{
    public function show(): Response
    {
        $domain = Domain::query()->where('is_primary', true)->first();
        $baseUrl = $domain === null ? '' : 'https://'.$domain->domain;

        // A page is "live" the same way StorefrontPageController decides
        // it — ActivePageVersion existing, not `Page.status` (publishing
        // never touches that field; see that controller's docblock).
        $livePageIds = ActivePageVersion::query()->pluck('page_id');

        $urls = Page::query()->whereIn('id', $livePageIds)->pluck('slug')
            ->map(fn (string $slug) => "{$baseUrl}/pages/{$slug}")
            ->concat(
                BlogPost::query()->where('status', BlogPostStatus::Published->value)->pluck('slug')
                    ->map(fn (string $slug) => "{$baseUrl}/blog/posts/{$slug}"),
            );

        // Built as a plain string, not a Blade view or a PHP comment
        // containing a literal close tag: an XML declaration opens with
        // "<" followed by "?", and a closing "?" followed by ">" is a
        // real PHP close tag wherever it appears in the raw source —
        // including inside a "//" comment, which only ends at end of
        // line OR at that close tag, whichever comes first. Getting this
        // wrong (as an earlier version of this file did, both as a Blade
        // view and in this very comment) silently drops the rest of the
        // file out of PHP mode into literal HTML output.
        $locs = $urls->map(fn (string $url) => '<url><loc>'.htmlspecialchars($url, ENT_XML1 | ENT_QUOTES).'</loc></url>')->implode('');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$locs.'</urlset>';

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
