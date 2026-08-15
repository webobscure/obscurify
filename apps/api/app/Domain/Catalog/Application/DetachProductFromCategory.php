<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductCategory;
use App\Shared\Commerce\Application\RecordOutboxEvent;

final class DetachProductFromCategory
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    public function handle(Category $category, Product $product): void
    {
        ProductCategory::query()
            ->where('category_id', $category->id)
            ->where('product_id', $product->id)
            ->delete();

        $this->recordOutboxEvent->handle('ProductUpdated', 'Product', $product->id, ['product_id' => $product->id]);
    }
}
