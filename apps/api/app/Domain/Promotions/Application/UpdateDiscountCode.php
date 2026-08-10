<?php

namespace App\Domain\Promotions\Application;

use App\Domain\Promotions\Models\DiscountCode;

final class UpdateDiscountCode
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(DiscountCode $discountCode, array $data): DiscountCode
    {
        $discountCode->update($data);

        return $discountCode;
    }
}
