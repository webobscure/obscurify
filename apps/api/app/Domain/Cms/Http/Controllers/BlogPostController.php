<?php

namespace App\Domain\Cms\Http\Controllers;

use App\Domain\Cms\Enums\BlogPostStatus;
use App\Domain\Cms\Http\Requests\StoreBlogPostRequest;
use App\Domain\Cms\Http\Requests\UpdateBlogPostRequest;
use App\Domain\Cms\Http\Resources\BlogPostResource;
use App\Domain\Cms\Models\Blog;
use App\Domain\Cms\Models\BlogPost;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class BlogPostController extends Controller
{
    public function index(Blog $blog): AnonymousResourceCollection
    {
        return BlogPostResource::collection(
            $blog->posts()->with('author')->orderByDesc('created_at')->get(),
        );
    }

    public function store(StoreBlogPostRequest $request, Blog $blog): BlogPostResource
    {
        $data = $request->validated();
        $data['status'] = $data['scheduled_at'] ?? null ? BlogPostStatus::Scheduled->value : BlogPostStatus::Draft->value;

        $post = $blog->posts()->create($data);

        return new BlogPostResource($post->load('author'));
    }

    public function show(BlogPost $blogPost): BlogPostResource
    {
        return new BlogPostResource($blogPost->load('author'));
    }

    public function update(UpdateBlogPostRequest $request, BlogPost $blogPost): BlogPostResource
    {
        $blogPost->update($request->validated());

        return new BlogPostResource($blogPost->load('author'));
    }

    public function publish(BlogPost $blogPost): JsonResponse
    {
        $blogPost->update([
            'status' => BlogPostStatus::Published->value,
            'published_at' => now(),
            'scheduled_at' => null,
        ]);

        return (new BlogPostResource($blogPost->load('author')))->response();
    }

    public function destroy(BlogPost $blogPost): Response
    {
        $blogPost->delete();

        return response()->noContent();
    }
}
