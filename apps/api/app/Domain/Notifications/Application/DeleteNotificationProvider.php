<?php

namespace App\Domain\Notifications\Application;

use App\Domain\Notifications\Models\NotificationProvider;

final class DeleteNotificationProvider
{
    public function handle(NotificationProvider $provider): void
    {
        $provider->delete();
    }
}
