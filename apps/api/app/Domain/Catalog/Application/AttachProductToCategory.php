<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductCategory;
use App\Shared\Commerce\Application\RecordOutboxEvent;

final class AttachProductToCategory
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    /**
     * Both $category and $product are resolved through route model
     * binding under the same active TenantContext, so a cross-tenant pair
     * can never reach here. Idempotent: attaching an already-member
     * product is a no-op rather than a duplicate-key error. Fires
     * `ProductUpdated` — see AttachProductToCollection for why.
     */
    public function handle(Category $category, Product $product): ProductCategory
    {
        $link = ProductCategory::query()->firstOrCreate([
            'category_id' => $category->id,
            'product_id' => $product->id,
        ]);

        $this->recordOutboxEvent->handle('ProductUpdated', 'Product', $product->id, ['product_id' => $product->id]);

        return $link;
    }
}
