<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Models\ProductVariant;

final class DeleteProductVariant
{
    public function handle(ProductVariant $variant): void
    {
        $variant->delete();
    }
}
