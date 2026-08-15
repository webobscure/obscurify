<?php

namespace App\Domain\Notifications\Application;

use App\Domain\Notifications\Models\NotificationProvider;

final class CreateNotificationProvider
{
    /**
     * @param  array{code: string, name: string, is_enabled?: bool, config?: array<string, mixed>}  $data
     */
    public function handle(array $data): NotificationProvider
    {
        return NotificationProvider::query()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'is_enabled' => $data['is_enabled'] ?? true,
            'config' => $data['config'] ?? [],
        ]);
    }
}
