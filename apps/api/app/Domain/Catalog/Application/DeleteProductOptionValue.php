<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Models\ProductOptionValue;
use App\Domain\Catalog\Models\ProductVariantOptionValue;
use Illuminate\Validation\ValidationException;

final class DeleteProductOptionValue
{
    public function handle(ProductOptionValue $value): void
    {
        $inUse = ProductVariantOptionValue::query()
            ->where('product_option_value_id', $value->id)
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'value' => 'Cannot delete an option value that is used by existing variants.',
            ]);
        }

        $value->delete();
    }
}
