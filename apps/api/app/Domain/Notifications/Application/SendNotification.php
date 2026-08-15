<?php

namespace App\Domain\Notifications\Application;

use App\Domain\Notifications\Enums\NotificationChannelType;
use App\Domain\Notifications\Enums\NotificationTriggerSource;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Models\NotificationTemplate;
use App\Domain\Notifications\Support\NotificationDispatchRequest;
use App\Domain\Notifications\Support\NotificationRecipientInput;
use App\Domain\Stores\Models\Store;

/**
 * The Admin UI trigger source (spec section 7) — `POST /notifications`
 * lets a merchant compose and send a message directly, bypassing
 * template/event routing entirely if they choose to (literal
 * subject/body). Thin wrapper around NotificationDispatcher; exists as
 * its own application service only so the controller stays a pure
 * request/response translator, matching every other Application-layer
 * boundary in this codebase.
 */
final class SendNotification
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    /**
     * @param  array{channel: string, customer_id?: string, address?: string, template_id?: string, subject?: string, body_text?: string, body_html?: string}  $data
     */
    public function handle(Store $store, array $data): Notification
    {
        $channel = NotificationChannelType::from($data['channel']);
        $template = isset($data['template_id']) ? NotificationTemplate::query()->find($data['template_id']) : null;

        $recipient = isset($data['customer_id'])
            ? NotificationRecipientInput::customer($data['customer_id'], $data['address'] ?? null)
            : NotificationRecipientInput::adHoc($data['address'] ?? null);

        return $this->dispatcher->dispatch($store, new NotificationDispatchRequest(
            channel: $channel,
            triggeredBy: NotificationTriggerSource::Admin,
            recipients: [$recipient],
            template: $template,
            subject: $data['subject'] ?? null,
            bodyText: $data['body_text'] ?? null,
            bodyHtml: $data['body_html'] ?? null,
        ));
    }
}
