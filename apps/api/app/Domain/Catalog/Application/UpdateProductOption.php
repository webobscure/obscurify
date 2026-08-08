<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Models\ProductOption;

final class UpdateProductOption
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(ProductOption $option, array $data): ProductOption
    {
        $option->update($data);

        return $option;
    }
}
