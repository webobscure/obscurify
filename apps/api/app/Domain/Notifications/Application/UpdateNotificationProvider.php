<?php

namespace App\Domain\Notifications\Application;

use App\Domain\Notifications\Models\NotificationProvider;

final class UpdateNotificationProvider
{
    /**
     * @param  array{name?: string, is_enabled?: bool, config?: array<string, mixed>}  $data
     */
    public function handle(NotificationProvider $provider, array $data): NotificationProvider
    {
        $provider->fill([
            'name' => $data['name'] ?? $provider->name,
            'is_enabled' => $data['is_enabled'] ?? $provider->is_enabled,
            'config' => $data['config'] ?? $provider->config,
        ])->save();

        return $provider->fresh();
    }
}
