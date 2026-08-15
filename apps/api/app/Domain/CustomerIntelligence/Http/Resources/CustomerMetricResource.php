<?php

namespace App\Domain\CustomerIntelligence\Http\Resources;

use App\Domain\CustomerIntelligence\Models\CustomerMetric;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `return_rate` is converted from stored basis points back to
 * percentage points here — see CustomerMetric's migration docblock for
 * why the column itself is an integer.
 *
 * @mixin CustomerMetric
 */
final class CustomerMetricResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_spent_amount' => $this->total_spent_amount,
            'average_order_value_amount' => $this->average_order_value_amount,
            'order_count' => $this->order_count,
            'refund_count' => $this->refund_count,
            'return_count' => $this->return_count,
            'return_rate' => $this->return_rate_bps / 100,
            'lifetime_value_amount' => $this->lifetime_value_amount,
            'currency' => $this->currency,
            'first_order_at' => $this->first_order_at,
            'last_order_at' => $this->last_order_at,
            'days_since_last_order' => $this->daysSinceLastOrder(),
            'computed_at' => $this->computed_at,
        ];
    }
}
