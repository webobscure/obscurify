<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Models\ProductOption;
use App\Domain\Catalog\Models\ProductOptionValue;

final class CreateProductOptionValue
{
    /**
     * @param  array{value: string, position?: int}  $data
     */
    public function handle(ProductOption $option, array $data): ProductOptionValue
    {
        $data['position'] ??= $option->values()->count();

        // product_option_id is always taken from the tenant-scoped
        // route-bound ProductOption, never accepted from client input.
        return ProductOptionValue::query()->create([
            'product_option_id' => $option->id,
            ...$data,
        ]);
    }
}
