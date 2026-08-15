<?php

namespace App\Domain\Notifications\Jobs;

use App\Domain\Notifications\Enums\NotificationDeliveryStatus;
use App\Domain\Notifications\Exceptions\UnknownNotificationProviderException;
use App\Domain\Notifications\Models\NotificationDelivery;
use App\Domain\Notifications\Support\NotificationProviderRegistry;
use App\Domain\Notifications\Support\RecalculateNotificationStatus;
use App\Domain\Notifications\Support\RenderedNotificationMessage;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Sends one NotificationDelivery through its assigned provider — the
 * notification-domain sibling of DeliverWebhookJob, same retry
 * bookkeeping (MAX_ATTEMPTS/backoff tracked as columns on the row
 * itself, not Laravel's queue-level $tries, since retry timing must
 * survive across separate job dispatches). Never throws on a failed
 * send attempt — that's business as usual for a provider (or an
 * unregistered provider code), not a queue-worker failure.
 *
 * Claims the delivery via a guarded `UPDATE ... WHERE status IN (...)`
 * before doing anything else — the same optimistic-claim pattern
 * WorkflowRunner uses for WorkflowExecution (see
 * docs/adr/025-automation-engine.md Decision 3), necessary here because
 * a manual "Retry" click from the admin Delivery Log and an automatic
 * `notifications:retry-failed` pass can both dispatch this job for the
 * same delivery id at nearly the same time; without the claim, both
 * would read the same starting `attempt_count` and race to `update()`,
 * corrupting the retry count and potentially double-sending.
 */
final class SendNotificationDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const int MAX_ATTEMPTS = 6;

    /**
     * @var int[]
     */
    private const array BACKOFF_SECONDS = [10, 30, 60, 300, 900, 3600];

    public function __construct(public readonly string $notificationDeliveryId) {}

    public function handle(TenantContext $tenantContext, NotificationProviderRegistry $registry, RecalculateNotificationStatus $recalculateStatus): void
    {
        $delivery = NotificationDelivery::withoutGlobalScopes()->find($this->notificationDeliveryId);

        if ($delivery === null) {
            return;
        }

        $store = Store::query()->find($delivery->store_id);

        if ($store === null) {
            return;
        }

        $claimed = NotificationDelivery::withoutGlobalScopes()
            ->whereKey($delivery->id)
            ->whereIn('status', [NotificationDeliveryStatus::Pending->value, NotificationDeliveryStatus::Failed->value])
            ->update(['status' => NotificationDeliveryStatus::Sending->value]);

        if ($claimed !== 1) {
            return;
        }

        $tenantContext->scope($store, function () use ($delivery, $registry, $recalculateStatus) {
            $delivery = $delivery->fresh();
            $notification = $delivery->notification;
            $recipient = $delivery->recipient;
            $provider = $delivery->provider;

            if ($notification === null || $recipient === null || $provider === null) {
                $this->recordAttempt($delivery, $delivery->attempt_count + 1, false, 'Delivery is missing its notification, recipient, or provider.');
                $recalculateStatus->handle($notification ?? $delivery->notification()->withoutGlobalScopes()->first());

                return;
            }

            $message = new RenderedNotificationMessage(
                address: $recipient->address,
                subject: $notification->subject,
                bodyText: $notification->body_text,
                bodyHtml: $notification->body_html,
            );

            $attemptNumber = $delivery->attempt_count + 1;

            try {
                $contract = $registry->resolve($provider->code);
                $result = $contract->send($delivery, $message, $provider->config ?? []);

                $this->recordAttempt($delivery, $attemptNumber, $result->success, $result->errorMessage, $result->externalId, $result->responseMeta);
            } catch (UnknownNotificationProviderException $e) {
                $this->recordAttempt($delivery, $attemptNumber, false, $e->getMessage());
            } catch (Throwable $e) {
                $this->recordAttempt($delivery, $attemptNumber, false, substr($e->getMessage(), 0, 255));
            }

            $recalculateStatus->handle($notification);
        });
    }

    /**
     * @param  array<string, mixed>  $responseMeta
     */
    private function recordAttempt(NotificationDelivery $delivery, int $attemptNumber, bool $succeeded, ?string $errorMessage, ?string $externalId = null, array $responseMeta = []): void
    {
        if ($succeeded) {
            $delivery->update([
                'attempt_count' => $attemptNumber,
                'error_message' => null,
                'last_attempted_at' => now(),
                'delivered_at' => now(),
                'status' => NotificationDeliveryStatus::Succeeded->value,
                'response_meta' => [...$responseMeta, 'external_id' => $externalId],
            ]);

            return;
        }

        $exhausted = $attemptNumber >= self::MAX_ATTEMPTS;

        $delivery->update([
            'attempt_count' => $attemptNumber,
            'error_message' => $errorMessage,
            'last_attempted_at' => now(),
            'status' => $exhausted ? NotificationDeliveryStatus::Exhausted->value : NotificationDeliveryStatus::Failed->value,
            'next_retry_at' => $exhausted ? null : now()->addSeconds(self::BACKOFF_SECONDS[$attemptNumber - 1] ?? 3600),
        ]);
    }
}
