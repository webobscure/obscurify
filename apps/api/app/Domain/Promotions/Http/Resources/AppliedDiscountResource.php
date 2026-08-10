<?php

namespace App\Domain\Promotions\Http\Resources;

use App\Domain\Promotions\Support\AppliedDiscount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AppliedDiscount
 */
final class AppliedDiscountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'promotion_id' => $this->promotion->id,
            'promotion_name' => $this->promotion->name,
            'discount_code' => $this->discountCode?->code,
            'action_type' => $this->actionType->value,
            'target' => $this->target->value,
            'amount' => $this->amount,
            'product_variant_id' => $this->productVariantId,
        ];
    }
}
