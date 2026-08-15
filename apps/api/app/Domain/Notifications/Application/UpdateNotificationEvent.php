<?php

namespace App\Domain\Notifications\Application;

use App\Domain\Notifications\Models\NotificationEvent;

final class UpdateNotificationEvent
{
    /**
     * @param  array{event_type?: string, channel?: string, template_id?: string, is_enabled?: bool}  $data
     */
    public function handle(NotificationEvent $event, array $data): NotificationEvent
    {
        $event->fill([
            'event_type' => $data['event_type'] ?? $event->event_type,
            'channel' => $data['channel'] ?? $event->channel->value,
            'template_id' => $data['template_id'] ?? $event->template_id,
            'is_enabled' => $data['is_enabled'] ?? $event->is_enabled,
        ])->save();

        return $event->fresh();
    }
}
