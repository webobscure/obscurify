<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Catalog\Models\ProductVariantOptionValue;
use App\Domain\Inventory\Models\InventoryItem;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateProductVariant
{
    use ResolvesVariantOptionValues;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Product $product, array $data): ProductVariant
    {
        $optionValueIds = $data['option_value_ids'] ?? [];
        unset($data['option_value_ids']);

        $optionValues = $this->resolveOptionValues($product, $optionValueIds);
        $signature = $this->signature($optionValueIds);

        $data['title'] ??= $optionValues->isEmpty()
            ? 'Default Title'
            : $optionValues->pluck('value')->implode(' / ');
        $data['currency'] ??= $product->store->default_currency;
        $data['status'] ??= ProductStatus::Active->value;

        try {
            return DB::transaction(function () use ($product, $data, $signature, $optionValueIds) {
                // product_id is always taken from the tenant-scoped
                // route-bound Product, never accepted from client input.
                $variant = ProductVariant::query()->create([
                    'product_id' => $product->id,
                    'option_signature' => $signature,
                    ...$data,
                ]);

                foreach ($optionValueIds as $optionValueId) {
                    ProductVariantOptionValue::query()->create([
                        'product_variant_id' => $variant->id,
                        'product_option_value_id' => $optionValueId,
                    ]);
                }

                // Every variant is inventory-tracked by default — inventory
                // levels/movements are only ever created against an
                // InventoryItem, never a bare variant.
                InventoryItem::query()->create([
                    'product_variant_id' => $variant->id,
                    'tracked' => true,
                    'requires_shipping' => true,
                ]);

                return $variant;
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages([
                'option_value_ids' => 'A variant with this combination of options already exists.',
            ]);
        }
    }
}
