<?php

namespace App\Domain\Storefront\Http\Controllers;

use App\Domain\Catalog\Models\Category;
use App\Domain\Storefront\Http\Resources\StorefrontCategoryResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class StorefrontCategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('position')
            ->get();

        return StorefrontCategoryResource::collection($categories);
    }

    public function show(string $slug): StorefrontCategoryResource
    {
        $category = Category::query()
            ->where('slug', $slug)
            ->with('children')
            ->firstOrFail();

        return new StorefrontCategoryResource($category);
    }
}
