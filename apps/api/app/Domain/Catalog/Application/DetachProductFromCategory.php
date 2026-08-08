<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductCategory;

final class DetachProductFromCategory
{
    public function handle(Category $category, Product $product): void
    {
        ProductCategory::query()
            ->where('category_id', $category->id)
            ->where('product_id', $product->id)
            ->delete();
    }
}
