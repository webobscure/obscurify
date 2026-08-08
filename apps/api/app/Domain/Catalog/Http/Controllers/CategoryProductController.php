<?php

namespace App\Domain\Catalog\Http\Controllers;

use App\Domain\Catalog\Application\AttachProductToCategory;
use App\Domain\Catalog\Application\DetachProductFromCategory;
use App\Domain\Catalog\Http\Resources\CategoryResource;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class CategoryProductController extends Controller
{
    public function store(Category $category, Product $product, AttachProductToCategory $action): CategoryResource
    {
        $action->handle($category, $product);

        return new CategoryResource($category->load('products'));
    }

    public function destroy(Category $category, Product $product, DetachProductFromCategory $action): Response
    {
        $action->handle($category, $product);

        return response()->noContent();
    }
}
