<?php

namespace App\Domain\RussianCommerce\Application;

use App\Domain\RussianCommerce\Models\FiscalizationSettings;

final class UpdateFiscalizationSettings
{
    /**
     * @param  array{active_provider_id?: ?string, default_vat_rate?: string, receipts_required?: bool}  $data
     */
    public function handle(FiscalizationSettings $settings, array $data): FiscalizationSettings
    {
        $settings->fill([
            'active_provider_id' => array_key_exists('active_provider_id', $data) ? $data['active_provider_id'] : $settings->active_provider_id,
            'default_vat_rate' => $data['default_vat_rate'] ?? $settings->default_vat_rate,
            'receipts_required' => $data['receipts_required'] ?? $settings->receipts_required,
        ])->save();

        return $settings->fresh();
    }
}
