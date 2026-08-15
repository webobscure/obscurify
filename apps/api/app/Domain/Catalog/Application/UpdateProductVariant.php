<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Catalog\Models\ProductVariantOptionValue;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateProductVariant
{
    use ResolvesVariantOptionValues;

    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(ProductVariant $variant, array $data): ProductVariant
    {
        $hasOptionValues = array_key_exists('option_value_ids', $data);
        $optionValueIds = $data['option_value_ids'] ?? [];
        unset($data['option_value_ids']);

        if ($hasOptionValues) {
            $this->resolveOptionValues($variant->product, $optionValueIds);
            $data['option_signature'] = $this->signature($optionValueIds);
        }

        $priceChanged = array_key_exists('price_amount', $data) && $data['price_amount'] !== $variant->price_amount;

        try {
            $variant = DB::transaction(function () use ($variant, $data, $hasOptionValues, $optionValueIds) {
                $variant->update($data);

                if ($hasOptionValues) {
                    ProductVariantOptionValue::query()->where('product_variant_id', $variant->id)->delete();

                    foreach ($optionValueIds as $optionValueId) {
                        ProductVariantOptionValue::query()->create([
                            'product_variant_id' => $variant->id,
                            'product_option_value_id' => $optionValueId,
                        ]);
                    }
                }

                return $variant;
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages([
                'option_value_ids' => 'A variant with this combination of options already exists.',
            ]);
        }

        $this->recordOutboxEvent->handle('VariantUpdated', 'Product', $variant->product_id, ['product_id' => $variant->product_id, 'variant_id' => $variant->id]);

        if ($priceChanged) {
            $this->recordOutboxEvent->handle('PriceChanged', 'Product', $variant->product_id, ['product_id' => $variant->product_id, 'variant_id' => $variant->id]);
        }

        return $variant;
    }
}
