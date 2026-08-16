<?php

namespace App\Domain\RussianCommerce\Application;

use App\Domain\RussianCommerce\Models\FiscalizationProvider;

final class CreateFiscalizationProvider
{
    /**
     * @param  array{code: string, name: string, is_enabled?: bool, config?: array<string, mixed>, credentials?: ?string}  $data
     */
    public function handle(array $data): FiscalizationProvider
    {
        return FiscalizationProvider::query()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'is_enabled' => $data['is_enabled'] ?? true,
            'config' => $data['config'] ?? [],
            'credentials' => $data['credentials'] ?? null,
        ]);
    }
}
