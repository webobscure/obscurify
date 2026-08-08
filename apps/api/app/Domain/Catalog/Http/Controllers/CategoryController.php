<?php

namespace App\Domain\Catalog\Http\Controllers;

use App\Domain\Catalog\Application\CreateCategory;
use App\Domain\Catalog\Application\DeleteCategory;
use App\Domain\Catalog\Application\UpdateCategory;
use App\Domain\Catalog\Http\Requests\StoreCategoryRequest;
use App\Domain\Catalog\Http\Requests\UpdateCategoryRequest;
use App\Domain\Catalog\Http\Resources\CategoryResource;
use App\Domain\Catalog\Models\Category;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class CategoryController extends Controller
{
    /**
     * Scoped to the active tenant by Category's BelongsToTenant global
     * scope — this can never return another store's categories.
     */
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::query()->orderBy('position')->get();

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request, CreateCategory $action): CategoryResource
    {
        $category = $action->handle($request->validated());

        return new CategoryResource($category);
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category->load('children'));
    }

    public function update(UpdateCategoryRequest $request, Category $category, UpdateCategory $action): CategoryResource
    {
        $category = $action->handle($category, $request->validated());

        return new CategoryResource($category);
    }

    public function destroy(Category $category, DeleteCategory $action): Response
    {
        $action->handle($category);

        return response()->noContent();
    }
}
