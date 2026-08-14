<?php

namespace App\Domain\Cms\Http\Controllers;

use App\Domain\Cms\Http\Requests\StoreBlogRequest;
use App\Domain\Cms\Http\Requests\UpdateBlogRequest;
use App\Domain\Cms\Http\Resources\BlogResource;
use App\Domain\Cms\Models\Blog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class BlogController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return BlogResource::collection(Blog::query()->withCount('posts')->orderByDesc('created_at')->get());
    }

    public function show(Blog $blog): BlogResource
    {
        return new BlogResource($blog->loadCount('posts'));
    }

    public function store(StoreBlogRequest $request): BlogResource
    {
        return new BlogResource(Blog::query()->create($request->validated())->loadCount('posts'));
    }

    public function update(UpdateBlogRequest $request, Blog $blog): BlogResource
    {
        $blog->update($request->validated());

        return new BlogResource($blog->loadCount('posts'));
    }

    public function destroy(Blog $blog): Response
    {
        $blog->delete();

        return response()->noContent();
    }
}
