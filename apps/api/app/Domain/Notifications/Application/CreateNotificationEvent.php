<?php

namespace App\Domain\Notifications\Application;

use App\Domain\Notifications\Models\NotificationEvent;

final class CreateNotificationEvent
{
    /**
     * @param  array{event_type: string, channel: string, template_id: string, is_enabled?: bool}  $data
     */
    public function handle(array $data): NotificationEvent
    {
        return NotificationEvent::query()->create([
            'event_type' => $data['event_type'],
            'channel' => $data['channel'],
            'template_id' => $data['template_id'],
            'is_enabled' => $data['is_enabled'] ?? true,
        ]);
    }
}
