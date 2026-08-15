<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Models\ProductVariant;
use App\Shared\Commerce\Application\RecordOutboxEvent;

final class DeleteProductVariant
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    public function handle(ProductVariant $variant): void
    {
        $productId = $variant->product_id;
        $variantId = $variant->id;

        $variant->delete();

        $this->recordOutboxEvent->handle('VariantUpdated', 'Product', $productId, ['product_id' => $productId, 'variant_id' => $variantId]);
    }
}
