<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductOption;

final class CreateProductOption
{
    /**
     * @param  array{name: string, position?: int}  $data
     */
    public function handle(Product $product, array $data): ProductOption
    {
        $data['position'] ??= $product->options()->count();

        // product_id is always taken from the tenant-scoped route-bound
        // Product, never accepted from client input.
        return ProductOption::query()->create([
            'product_id' => $product->id,
            ...$data,
        ]);
    }
}
