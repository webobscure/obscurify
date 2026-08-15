<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Models\Product;
use App\Shared\Commerce\Application\RecordOutboxEvent;

final class UpdateProduct
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Product $product, array $data): Product
    {
        $statusChanged = array_key_exists('status', $data) && $data['status'] !== $product->status->value;

        $product->update($data);

        $this->recordOutboxEvent->handle('ProductUpdated', 'Product', $product->id, ['product_id' => $product->id]);

        if ($statusChanged) {
            $this->recordOutboxEvent->handle('VisibilityChanged', 'Product', $product->id, ['product_id' => $product->id, 'status' => $product->status->value]);
        }

        return $product;
    }
}
