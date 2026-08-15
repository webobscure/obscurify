<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Models\Product;
use App\Shared\Commerce\Application\RecordOutboxEvent;

final class DeleteProduct
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    public function handle(Product $product): void
    {
        $productId = $product->id;

        $product->delete();

        $this->recordOutboxEvent->handle('ProductDeleted', 'Product', $productId, ['product_id' => $productId]);
    }
}
