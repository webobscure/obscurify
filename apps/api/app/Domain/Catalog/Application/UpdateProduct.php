<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Models\Product;

final class UpdateProduct
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Product $product, array $data): Product
    {
        $product->update($data);

        return $product;
    }
}
