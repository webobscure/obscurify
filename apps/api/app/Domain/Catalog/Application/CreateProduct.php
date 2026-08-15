<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Str;

final class CreateProduct
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    /**
     * @param  array{title: string, slug?: string, description?: string|null, status?: string}  $data
     */
    public function handle(array $data): Product
    {
        $data['slug'] ??= Str::slug($data['title']);
        $data['status'] ??= ProductStatus::Draft->value;

        // store_id is never accepted here: Product::creating() forces it
        // from TenantContext regardless of what this array contains.
        $product = Product::query()->create($data);

        $this->recordOutboxEvent->handle('ProductCreated', 'Product', $product->id, ['product_id' => $product->id]);

        return $product;
    }
}
