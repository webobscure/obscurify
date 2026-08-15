<?php

namespace App\Domain\Notifications\Support;

/**
 * The fully-rendered (variables already interpolated) content handed to
 * a NotificationProviderContract::send() call, plus the resolved
 * recipient address — everything a provider needs and nothing it
 * shouldn't reach into the Notification/NotificationRecipient models
 * for.
 */
final readonly class RenderedNotificationMessage
{
    public function __construct(
        public ?string $address,
        public ?string $subject,
        public string $bodyText,
        public ?string $bodyHtml,
    ) {}
}
