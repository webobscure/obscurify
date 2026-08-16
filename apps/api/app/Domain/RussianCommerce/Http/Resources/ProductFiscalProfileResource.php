<?php

namespace App\Domain\RussianCommerce\Http\Resources;

use App\Domain\RussianCommerce\Models\ProductFiscalProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductFiscalProfile
 */
final class ProductFiscalProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fiscalizable_type' => $this->fiscalizable_type->value,
            'fiscalizable_id' => $this->fiscalizable_id,
            'vat_rate' => $this->vat_rate->value,
            'payment_subject' => $this->payment_subject->value,
            'unit_of_measure' => $this->unit_of_measure,
        ];
    }
}
