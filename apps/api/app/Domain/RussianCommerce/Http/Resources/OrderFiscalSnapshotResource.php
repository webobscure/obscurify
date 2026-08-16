<?php

namespace App\Domain\RussianCommerce\Http\Resources;

use App\Domain\RussianCommerce\Models\OrderFiscalSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderFiscalSnapshot
 */
final class OrderFiscalSnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'seller_legal_entity_type' => $this->seller_legal_entity_type->value,
            'seller_legal_name' => $this->seller_legal_name,
            'seller_inn' => $this->seller_inn,
            'seller_kpp' => $this->seller_kpp,
            'vat_rate' => $this->vat_rate->value,
            'vat_amount' => $this->vat_amount,
            'receipt_required' => $this->receipt_required,
        ];
    }
}
