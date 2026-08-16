<?php

namespace App\Domain\RussianCommerce\Http\Resources;

use App\Domain\RussianCommerce\Models\FiscalReceiptItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FiscalReceiptItem
 */
final class FiscalReceiptItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'price_amount' => $this->price_amount,
            'amount' => $this->amount,
            'vat_rate' => $this->vat_rate->value,
            'payment_method' => $this->payment_method->value,
            'payment_subject' => $this->payment_subject->value,
            'unit_of_measure' => $this->unit_of_measure,
        ];
    }
}
