<?php

namespace App\Domain\Promotions\Http\Resources;

use App\Domain\Promotions\Models\DiscountApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Renders the Order's discount snapshot (spec section 8) — always from
 * this row's own promotion_name/code copy, never a live Promotion/
 * DiscountCode lookup, so it's safe to use on both the admin OrderResource
 * and the storefront-safe OrderConfirmationResource.
 *
 * @mixin DiscountApplication
 */
final class DiscountApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'promotion_name' => $this->promotion_name,
            'code' => $this->code,
            'action_type' => $this->action_type->value,
            'target' => $this->target->value,
            'order_item_id' => $this->order_item_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }
}
