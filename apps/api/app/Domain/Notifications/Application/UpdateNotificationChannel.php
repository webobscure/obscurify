<?php

namespace App\Domain\Notifications\Application;

use App\Domain\Notifications\Models\NotificationChannel;

/**
 * Channels are seeded once per store (EnsureDefaultNotificationSetup),
 * never created ad hoc through the API — the fixed set of five from
 * NotificationChannelType is the whole point (spec section 3), so the
 * only mutation the admin "Channels" page needs is reassigning the
 * provider and/or toggling enabled.
 */
final class UpdateNotificationChannel
{
    /**
     * @param  array{provider_id?: ?string, is_enabled?: bool}  $data
     */
    public function handle(NotificationChannel $channel, array $data): NotificationChannel
    {
        $channel->fill([
            'provider_id' => array_key_exists('provider_id', $data) ? $data['provider_id'] : $channel->provider_id,
            'is_enabled' => $data['is_enabled'] ?? $channel->is_enabled,
        ])->save();

        return $channel->fresh();
    }
}
