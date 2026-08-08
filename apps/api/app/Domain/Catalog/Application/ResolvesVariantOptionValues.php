<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductOptionValue;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Shared validation for the set of ProductOptionValue ids a variant
 * selects: every id must belong to one of the product's own options, and a
 * variant may select at most one value per option.
 */
trait ResolvesVariantOptionValues
{
    /**
     * @param  array<int, string>  $optionValueIds
     * @return Collection<int, ProductOptionValue>
     */
    private function resolveOptionValues(Product $product, array $optionValueIds): Collection
    {
        if ($optionValueIds === []) {
            return new Collection;
        }

        $optionValues = ProductOptionValue::query()
            ->whereIn('id', $optionValueIds)
            ->whereHas('option', fn ($query) => $query->where('product_id', $product->id))
            ->get()
            ->keyBy('id');

        if ($optionValues->count() !== count(array_unique($optionValueIds))) {
            throw ValidationException::withMessages([
                'option_value_ids' => 'One or more option values do not belong to this product.',
            ]);
        }

        $optionIds = $optionValues->pluck('product_option_id');

        if ($optionIds->count() !== $optionIds->unique()->count()) {
            throw ValidationException::withMessages([
                'option_value_ids' => 'A variant can only select one value per option.',
            ]);
        }

        // Preserve the caller's id order so title generation reads naturally.
        return collect($optionValueIds)->map(fn (string $id) => $optionValues->get($id));
    }

    /**
     * @param  array<int, string>  $optionValueIds
     */
    private function signature(array $optionValueIds): string
    {
        $sorted = $optionValueIds;
        sort($sorted);

        return implode(',', $sorted);
    }
}
