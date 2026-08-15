<?php

namespace App\Domain\Customers\Http\Resources;

use App\Domain\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The order-history list row — deliberately lighter than
 * CustomerOrderResource (no items/payments/shipments/returns/refunds
 * eager-loaded), so listing a customer's orders stays O(1) queries
 * instead of N+1 across every nested relation. Full detail lives behind
 * GET /account/orders/{order}.
 *
 * @mixin Order
 */
final class CustomerOrderSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'currency' => $this->currency,
            'total_amount' => $this->total_amount,
            'order_status' => $this->order_status->value,
            'financial_status' => $this->financial_status->value,
            'fulfillment_status' => $this->fulfillment_status->value,
            'created_at' => $this->created_at,
        ];
    }
}
