<?php

namespace App\Domain\RussianCommerce\Application;

use App\Domain\RussianCommerce\Models\FiscalizationProvider;

final class UpdateFiscalizationProvider
{
    /**
     * @param  array{name?: string, is_enabled?: bool, config?: array<string, mixed>, credentials?: ?string}  $data
     */
    public function handle(FiscalizationProvider $provider, array $data): FiscalizationProvider
    {
        $provider->fill([
            'name' => $data['name'] ?? $provider->name,
            'is_enabled' => $data['is_enabled'] ?? $provider->is_enabled,
            'config' => $data['config'] ?? $provider->config,
            'credentials' => array_key_exists('credentials', $data) ? $data['credentials'] : $provider->credentials,
        ])->save();

        return $provider->fresh();
    }
}
