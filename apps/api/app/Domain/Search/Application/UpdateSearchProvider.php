<?php

namespace App\Domain\Search\Application;

use App\Domain\Search\Models\SearchProvider;

final class UpdateSearchProvider
{
    /**
     * @param  array{name?: string, is_enabled?: bool, config?: array<string, mixed>}  $data
     */
    public function handle(SearchProvider $provider, array $data): SearchProvider
    {
        $provider->fill([
            'name' => $data['name'] ?? $provider->name,
            'is_enabled' => $data['is_enabled'] ?? $provider->is_enabled,
            'config' => $data['config'] ?? $provider->config,
        ])->save();

        return $provider->fresh();
    }
}
