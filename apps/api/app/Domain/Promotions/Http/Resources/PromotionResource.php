<?php

namespace App\Domain\Promotions\Http\Resources;

use App\Domain\Promotions\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Promotion
 */
final class PromotionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'trigger_type' => $this->trigger_type->value,
            'stacking_mode' => $this->stacking_mode->value,
            'priority' => $this->priority,
            'status' => $this->status->value,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'rules' => PromotionRuleResource::collection($this->whenLoaded('rules')),
            'actions' => PromotionActionResource::collection($this->whenLoaded('actions')),
            'discount_codes' => DiscountCodeResource::collection($this->whenLoaded('discountCodes')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
