<?php

namespace App\Domain\Promotions\Http\Resources;

use App\Domain\Promotions\Models\DiscountCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DiscountCode
 */
final class DiscountCodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'promotion_id' => $this->promotion_id,
            'code' => $this->code,
            'usage_limit' => $this->usage_limit,
            'per_customer_limit' => $this->per_customer_limit,
            'usage_count' => $this->usage_count,
            'expires_at' => $this->expires_at,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
        ];
    }
}
