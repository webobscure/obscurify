<?php

namespace App\Domain\RussianCommerce\Http\Resources;

use App\Domain\RussianCommerce\Models\FiscalizationSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FiscalizationSettings
 */
final class FiscalizationSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'active_provider_id' => $this->active_provider_id,
            'active_provider' => new FiscalizationProviderResource($this->whenLoaded('activeProvider')),
            'default_vat_rate' => $this->default_vat_rate->value,
            'receipts_required' => $this->receipts_required,
        ];
    }
}
