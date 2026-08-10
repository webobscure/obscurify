<?php

namespace App\Domain\Promotions\Http\Resources;

use App\Domain\Promotions\Models\PromotionUsage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PromotionUsage
 */
final class PromotionUsageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'promotion_id' => $this->promotion_id,
            'discount_code_id' => $this->discount_code_id,
            'customer_id' => $this->customer_id,
            'order_id' => $this->order_id,
            'amount' => $this->amount,
            'created_at' => $this->created_at,
        ];
    }
}
