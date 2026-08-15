<?php

namespace App\Domain\Notifications\Support;

use App\Domain\Notifications\Enums\NotificationChannelType;
use App\Domain\Notifications\Enums\NotificationTriggerSource;
use App\Domain\Notifications\Models\NotificationTemplate;

/**
 * Everything NotificationDispatcher::dispatch() needs to create one
 * Notification and fan it out — either `template` (rendered against
 * `context`) or the literal `subject`/`bodyText`/`bodyHtml` fields
 * (also rendered against `context`, so an admin-composed ad-hoc message
 * can still use `{{customer.first_name}}`-style placeholders).
 */
final readonly class NotificationDispatchRequest
{
    /**
     * @param  list<NotificationRecipientInput>  $recipients
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public NotificationChannelType $channel,
        public NotificationTriggerSource $triggeredBy,
        public array $recipients,
        public array $context = [],
        public ?NotificationTemplate $template = null,
        public ?string $subject = null,
        public ?string $bodyText = null,
        public ?string $bodyHtml = null,
        public ?string $eventType = null,
        public ?string $relatedType = null,
        public ?string $relatedId = null,
        public ?string $workflowExecutionId = null,
    ) {}
}
