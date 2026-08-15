<?php

namespace App\Domain\Notifications\Application;

use App\Domain\Notifications\Models\NotificationEvent;

final class DeleteNotificationEvent
{
    public function handle(NotificationEvent $event): void
    {
        $event->delete();
    }
}
