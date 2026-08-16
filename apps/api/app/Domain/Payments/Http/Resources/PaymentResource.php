<?php

namespace App\Domain\Payments\Http\Resources;

use App\Domain\Payments\Models\Payment;
use App\Domain\RussianCommerce\Http\Resources\FiscalReceiptResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin-facing payment representation — no webhook secrets/raw signed
 * payloads (spec section 25/28).
 *
 * @mixin Payment
 */
final class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'order_id' => $this->order_id,
            'order_number' => $this->whenLoaded('order', fn () => $this->order->number),
            'provider' => $this->provider,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'authorized_amount' => $this->authorized_amount,
            'captured_amount' => $this->captured_amount,
            'refunded_amount' => $this->refunded_amount,
            'external_payment_id' => $this->external_payment_id,
            'payment_method' => $this->payment_method?->value,
            'method_metadata' => $this->method_metadata,
            'attempts' => PaymentAttemptResource::collection($this->whenLoaded('attempts')),
            'transactions' => PaymentTransactionResource::collection($this->whenLoaded('transactions')),
            'fiscal_receipt' => $this->whenLoaded('fiscalReceipt', fn () => $this->fiscalReceipt ? new FiscalReceiptResource($this->fiscalReceipt) : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
