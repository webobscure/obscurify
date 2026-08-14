<?php

namespace App\Domain\Apps\Http\Resources\Gateway;

use App\Domain\Payments\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
final class GatewayPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'provider' => $this->provider,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'created_at' => $this->created_at,
        ];
    }
}
