<?php

namespace App\Domain\Storefront\Http\Controllers;

use App\Domain\Cms\Enums\BlogPostStatus;
use App\Domain\Cms\Models\Blog;
use App\Domain\Cms\Models\BlogPost;
use App\Domain\Storefront\Http\Resources\StorefrontBlogPostResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public read access to a store's published blog posts. A `scheduled`
 * post never appears here even after its `scheduled_at` passes but
 * before `cms:publish-scheduled-posts` next runs — status, not the
 * timestamp, is authoritative for visibility (same "status is the
 * single source of truth for what's live" principle every other
 * publish flow in this codebase follows).
 */
final class StorefrontBlogController extends Controller
{
    public function index(string $blogSlug): AnonymousResourceCollection
    {
        $blog = Blog::query()->where('slug', $blogSlug)->firstOrFail();

        $posts = $blog->posts()
            ->where('status', BlogPostStatus::Published->value)
            ->with('author')
            ->orderByDesc('published_at')
            ->paginate();

        return StorefrontBlogPostResource::collection($posts);
    }

    public function show(string $postSlug): StorefrontBlogPostResource
    {
        $post = BlogPost::query()
            ->where('slug', $postSlug)
            ->where('status', BlogPostStatus::Published->value)
            ->with('author')
            ->firstOrFail();

        return new StorefrontBlogPostResource($post);
    }
}
