<?php

namespace App\Domain\Promotions\Application;

use App\Domain\Promotions\Enums\DiscountCodeStatus;
use App\Domain\Promotions\Models\DiscountCode;
use App\Domain\Promotions\Models\Promotion;

/**
 * A Promotion supports "single code, multiple codes" (spec section 6) by
 * having many DiscountCode rows — call this once per code; there is no
 * separate bulk-generate endpoint.
 */
final class CreateDiscountCode
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Promotion $promotion, array $data): DiscountCode
    {
        return DiscountCode::query()->create([
            'promotion_id' => $promotion->id,
            'code' => $data['code'],
            'usage_limit' => $data['usage_limit'] ?? null,
            'per_customer_limit' => $data['per_customer_limit'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'status' => $data['status'] ?? DiscountCodeStatus::Active->value,
        ]);
    }
}
