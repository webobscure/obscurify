<?php

namespace App\Domain\Notifications\Application;

use App\Domain\Notifications\Models\NotificationTemplate;

final class DeleteNotificationTemplate
{
    public function handle(NotificationTemplate $template): void
    {
        $template->delete();
    }
}
