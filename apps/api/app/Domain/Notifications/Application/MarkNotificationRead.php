<?php

namespace App\Domain\Notifications\Application;

use App\Domain\Notifications\Models\NotificationRecipient;

/**
 * Read/unread state lives on NotificationRecipient, not Notification —
 * see that model's docblock. Called from the customer portal
 * (`PATCH /account/notifications/{notification}/read`).
 */
final class MarkNotificationRead
{
    public function handle(NotificationRecipient $recipient): NotificationRecipient
    {
        if ($recipient->read_at === null) {
            $recipient->update(['read_at' => now()]);
        }

        return $recipient->fresh();
    }
}
