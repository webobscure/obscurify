<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Models\ProductOption;
use App\Domain\Catalog\Models\ProductVariantOptionValue;
use Illuminate\Validation\ValidationException;

final class DeleteProductOption
{
    public function handle(ProductOption $option): void
    {
        $inUse = ProductVariantOptionValue::query()
            ->whereIn('product_option_value_id', $option->values()->pluck('id'))
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'option' => 'Cannot delete an option that is used by existing variants.',
            ]);
        }

        $option->delete();
    }
}
