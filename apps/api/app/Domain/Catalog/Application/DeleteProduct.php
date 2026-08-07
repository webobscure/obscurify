<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Models\Product;

final class DeleteProduct
{
    public function handle(Product $product): void
    {
        $product->delete();
    }
}
