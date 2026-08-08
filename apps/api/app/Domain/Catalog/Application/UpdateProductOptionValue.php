<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Models\ProductOptionValue;

final class UpdateProductOptionValue
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(ProductOptionValue $value, array $data): ProductOptionValue
    {
        $value->update($data);

        return $value;
    }
}
