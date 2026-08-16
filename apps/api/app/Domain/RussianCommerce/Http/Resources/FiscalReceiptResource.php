<?php

namespace App\Domain\RussianCommerce\Http\Resources;

use App\Domain\RussianCommerce\Models\FiscalReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `customer_email`/`customer_phone`/`seller_inn`/`seller_kpp` are
 * exposed only to authenticated store admins (this resource is never
 * used on a storefront/public route — spec section 18/20).
 *
 * @mixin FiscalReceipt
 */
final class FiscalReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'payment_id' => $this->payment_id,
            'correction_of_id' => $this->correction_of_id,
            'operation' => $this->operation->value,
            'status' => $this->status->value,
            'provider' => $this->provider,
            'external_receipt_id' => $this->external_receipt_id,
            'seller_inn' => $this->seller_inn,
            'seller_kpp' => $this->seller_kpp,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'currency' => $this->currency,
            'total_amount' => $this->total_amount,
            'fiscalized_at' => $this->fiscalized_at,
            'error_message' => $this->error_message,
            'attempt_count' => $this->attempt_count,
            'items' => FiscalReceiptItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
