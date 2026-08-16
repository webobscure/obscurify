<?php

namespace App\Domain\Notifications\Application;

use App\Domain\Customers\Models\Customer;
use App\Domain\Notifications\Enums\NotificationChannelType;
use App\Domain\Notifications\Enums\NotificationDeliveryStatus;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Notifications\Jobs\SendNotificationDeliveryJob;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Models\NotificationChannel;
use App\Domain\Notifications\Models\NotificationDelivery;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Models\NotificationRecipient;
use App\Domain\Notifications\Support\NotificationDispatchRequest;
use App\Domain\Notifications\Support\NotificationRecipientInput;
use App\Domain\Notifications\Support\NotificationTemplateRenderer;
use App\Domain\Notifications\Support\RecalculateNotificationStatus;
use App\Domain\Notifications\Support\ResolveLocalizedNotificationTemplate;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\TenantContext;

/**
 * The single entry point every trigger source (Platform Events,
 * Automation, Admin UI, Apps SDK — spec section 7) goes through to send
 * a notification. Renders the message once, fans it out to N
 * recipients as N NotificationDelivery rows, gates each customer
 * recipient against their own NotificationPreference, and queues one
 * SendNotificationDeliveryJob per non-suppressed delivery.
 *
 * Self-scopes via TenantContext::scope() rather than trusting an
 * ambient scope — the same defensive pattern AnalyticsAggregator uses,
 * necessary here because callers include contexts that are already
 * store-scoped (the outbox subscriber, automation actions) and at
 * least one that isn't guaranteed to be (a future scheduled job).
 */
final class NotificationDispatcher
{
    public function __construct(
        private readonly NotificationTemplateRenderer $renderer,
        private readonly RecalculateNotificationStatus $recalculateStatus,
        private readonly TenantContext $tenantContext,
        private readonly ResolveLocalizedNotificationTemplate $resolveLocalizedTemplate,
    ) {}

    public function dispatch(Store $store, NotificationDispatchRequest $request): Notification
    {
        return $this->tenantContext->scope($store, function () use ($store, $request) {
            $baseTemplate = $request->template;
            $template = $baseTemplate !== null
                ? $this->resolveLocalizedTemplate->handle($baseTemplate, $this->primaryRecipientLocale($request), $store)
                : null;

            $subjectTemplate = ($template !== null ? $template->subject : null) ?? $request->subject;
            $bodyTextTemplate = ($template !== null ? $template->body_text : null) ?? $request->bodyText ?? '';
            $bodyHtmlTemplate = ($template !== null ? $template->body_html : null) ?? $request->bodyHtml;

            $notification = Notification::query()->create([
                'template_id' => $template !== null ? $template->id : null,
                'channel' => $request->channel->value,
                'event_type' => $request->eventType,
                'subject' => $subjectTemplate !== null ? $this->renderer->render($subjectTemplate, $request->context) : null,
                'body_text' => $this->renderer->render($bodyTextTemplate, $request->context),
                'body_html' => $bodyHtmlTemplate !== null ? $this->renderer->render($bodyHtmlTemplate, $request->context) : null,
                'related_type' => $request->relatedType,
                'related_id' => $request->relatedId,
                'workflow_execution_id' => $request->workflowExecutionId,
                'triggered_by' => $request->triggeredBy->value,
                'status' => NotificationStatus::Pending->value,
            ]);

            $channelConfig = NotificationChannel::query()->where('channel', $request->channel->value)->first();

            foreach ($request->recipients as $recipientInput) {
                $this->createRecipientAndDelivery($notification, $request->channel, $recipientInput, $channelConfig);
            }

            $this->recalculateStatus->handle($notification->fresh());

            return $notification->fresh(['recipients', 'deliveries']);
        });
    }

    private function createRecipientAndDelivery(
        Notification $notification,
        NotificationChannelType $channel,
        NotificationRecipientInput $recipientInput,
        ?NotificationChannel $channelConfig,
    ): void {
        $customer = $recipientInput->customerId !== null ? Customer::query()->find($recipientInput->customerId) : null;

        $address = $recipientInput->addressOverride ?? $this->resolveDefaultAddress($channel, $customer);

        $recipient = NotificationRecipient::query()->create([
            'notification_id' => $notification->id,
            'recipient_type' => $recipientInput->type->value,
            'customer_id' => $customer?->id,
            'address' => $address,
        ]);

        $idempotencyKey = "{$notification->id}:{$recipient->id}";

        if ($customer !== null && $this->isChannelSuppressedForCustomer($channel, $customer)) {
            NotificationDelivery::query()->create([
                'notification_id' => $notification->id,
                'recipient_id' => $recipient->id,
                'channel' => $channel->value,
                'provider_id' => $channelConfig?->provider_id,
                'status' => NotificationDeliveryStatus::Suppressed->value,
                'error_message' => 'Customer has disabled this notification channel.',
                'idempotency_key' => $idempotencyKey,
            ]);

            return;
        }

        if ($channelConfig === null || ! $channelConfig->is_enabled || $channelConfig->provider_id === null) {
            NotificationDelivery::query()->create([
                'notification_id' => $notification->id,
                'recipient_id' => $recipient->id,
                'channel' => $channel->value,
                'provider_id' => null,
                'status' => NotificationDeliveryStatus::Failed->value,
                'error_message' => 'No provider is configured or enabled for this channel.',
                'idempotency_key' => $idempotencyKey,
            ]);

            return;
        }

        $delivery = NotificationDelivery::query()->create([
            'notification_id' => $notification->id,
            'recipient_id' => $recipient->id,
            'channel' => $channel->value,
            'provider_id' => $channelConfig->provider_id,
            'status' => NotificationDeliveryStatus::Pending->value,
            'idempotency_key' => $idempotencyKey,
        ]);

        SendNotificationDeliveryJob::dispatch($delivery->id);
    }

    /**
     * A Notification renders its subject/body ONCE and shares that
     * rendering across every one of its recipients' NotificationDelivery
     * rows (a deliberate architectural constraint predating this
     * milestone) — locale resolution follows the same shape and picks
     * the FIRST customer recipient's saved locale, not a genuinely
     * per-recipient one. The realistic case this matters for
     * (Platform Events, spec section 7 of docs/architecture/notifications.md)
     * always dispatches with exactly one recipient anyway; a future
     * true bulk-compose-to-many-locales send is out of scope here — see
     * docs/architecture/localization.md "Consequences".
     */
    private function primaryRecipientLocale(NotificationDispatchRequest $request): ?string
    {
        foreach ($request->recipients as $recipientInput) {
            if ($recipientInput->customerId === null) {
                continue;
            }

            $customer = Customer::query()->find($recipientInput->customerId);

            if ($customer?->locale !== null) {
                return $customer->locale;
            }
        }

        return null;
    }

    private function resolveDefaultAddress(NotificationChannelType $channel, ?Customer $customer): ?string
    {
        if ($customer === null) {
            return null;
        }

        return match ($channel) {
            NotificationChannelType::Email => $customer->email,
            NotificationChannelType::Sms => $customer->phone,
            default => null,
        };
    }

    private function isChannelSuppressedForCustomer(NotificationChannelType $channel, Customer $customer): bool
    {
        $preference = NotificationPreference::query()->where('customer_id', $customer->id)->first();

        if ($preference === null) {
            return false;
        }

        return match ($channel) {
            NotificationChannelType::Email => ! $preference->email_enabled,
            NotificationChannelType::Sms => ! $preference->sms_enabled,
            NotificationChannelType::Push => ! $preference->push_enabled,
            default => false,
        };
    }
}
