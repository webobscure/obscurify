<?php

namespace App\Domain\Financial\Application;

use App\Domain\Financial\Enums\RefundStatus;
use App\Domain\Financial\Exceptions\UnknownRefundException;
use App\Domain\Financial\Models\FinancialEvent;
use App\Domain\Financial\Models\Refund;
use App\Domain\Financial\Models\RefundWebhookEvent;
use App\Domain\Financial\Support\RefundStateMachine;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Exceptions\MalformedWebhookPayloadException;
use App\Domain\Payments\Exceptions\WebhookReplayException;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Support\WebhookEvent;
use App\Domain\Stores\Models\Store;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The refund half of the shared payment/refund webhook pipeline (spec
 * section 7) — mirrors ProcessPaymentWebhook exactly: same replay-
 * tolerance check, same claim/poll idempotency shape (against
 * refund_webhook_events.(provider, external_event_id), a dedicated table
 * rather than reusing payment_webhook_events — see that migration's
 * docblock), same tenant-resolution-before-TenantContext problem solved
 * the same way, via (provider, external_refund_id) -> Refund -> store_id.
 */
final class ProcessRefundWebhook
{
    private const MAX_POLL_ATTEMPTS = 100;

    private const POLL_INTERVAL_MICROSECONDS = 20_000;

    public function __construct(
        private readonly RefundStateMachine $stateMachine,
        private readonly ApplyRefundCompletion $applyRefundCompletion,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    public function handle(string $providerCode, WebhookEvent $event, string $rawPayload): void
    {
        $tolerance = (int) config('payments.webhook.replay_tolerance_seconds');

        if ($event->occurredAt->lt(now()->subSeconds($tolerance))) {
            throw WebhookReplayException::make();
        }

        $externalRefundId = $event->externalRefundId;

        if ($externalRefundId === null) {
            throw MalformedWebhookPayloadException::make('missing field "external_refund_id".');
        }

        $payloadHash = hash('sha256', $rawPayload);

        $claimed = $this->claimOrAlreadyProcessed($providerCode, $event, $externalRefundId, $payloadHash);

        if (! $claimed) {
            // Already processed by another (possibly concurrent) delivery
            // of the exact same event — idempotent no-op.
            return;
        }

        $refund = Refund::withoutGlobalScopes()
            ->where('provider', $providerCode)
            ->where('provider_reference', $externalRefundId)
            ->first();

        if ($refund === null) {
            RefundWebhookEvent::query()
                ->where('provider', $providerCode)
                ->where('external_event_id', $event->eventId)
                ->update(['processed_at' => now()]);

            throw UnknownRefundException::forExternalId($providerCode, $externalRefundId);
        }

        $store = Store::query()->findOrFail($refund->store_id);

        app(TenantContext::class)->scope($store, function () use ($providerCode, $event, $refund, $store) {
            DB::transaction(function () use ($providerCode, $event, $refund, $store) {
                $lockedRefund = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

                $target = match ($event->status) {
                    'succeeded' => RefundStatus::Completed,
                    'failed' => RefundStatus::Failed,
                    default => throw MalformedWebhookPayloadException::make("unrecognized status \"{$event->status}\"."),
                };

                if (! $this->stateMachine->canTransition($lockedRefund->status, $target)) {
                    // Out-of-order or redundant delivery — never error the
                    // webhook endpoint over it, same discipline
                    // ProcessPaymentWebhook already established.
                    Log::warning('financial.refund_webhook.invalid_transition', [
                        'refund_id' => $lockedRefund->id,
                        'from' => $lockedRefund->status->value,
                        'to' => $target->value,
                    ]);
                } elseif ($target === RefundStatus::Completed) {
                    $lockedPayment = Payment::query()->whereKey($lockedRefund->payment_id)->lockForUpdate()->firstOrFail();
                    $lockedOrder = Order::query()->whereKey($lockedRefund->order_id)->lockForUpdate()->firstOrFail();

                    $this->applyRefundCompletion->handle($lockedRefund, $lockedPayment, $lockedOrder);
                } else {
                    $lockedRefund->update([
                        'status' => RefundStatus::Failed->value,
                        'processed_at' => now(),
                    ]);

                    FinancialEvent::query()->create([
                        'order_id' => $lockedRefund->order_id,
                        'type' => 'refund_failed',
                        'description' => "Refund #{$lockedRefund->number} failed.",
                        'occurred_at' => now(),
                    ]);

                    $this->recordOutboxEvent->handle('RefundFailed', 'Refund', $lockedRefund->id, [
                        'refund_id' => $lockedRefund->id,
                        'order_id' => $lockedRefund->order_id,
                        'store_id' => $lockedRefund->store_id,
                    ]);
                }

                RefundWebhookEvent::query()
                    ->where('provider', $providerCode)
                    ->where('external_event_id', $event->eventId)
                    ->update(['store_id' => $store->id, 'processed_at' => now()]);
            });
        });
    }

    private function claimOrAlreadyProcessed(string $provider, WebhookEvent $event, string $externalRefundId, string $payloadHash): bool
    {
        for ($attempt = 0; $attempt < self::MAX_POLL_ATTEMPTS; $attempt++) {
            try {
                DB::transaction(function () use ($provider, $event, $externalRefundId, $payloadHash) {
                    RefundWebhookEvent::query()->create([
                        'provider' => $provider,
                        'external_event_id' => $event->eventId,
                        'external_refund_id' => $externalRefundId,
                        'event_type' => $event->eventType,
                        'payload_hash' => $payloadHash,
                    ]);
                });

                return true;
            } catch (UniqueConstraintViolationException) {
                $existing = RefundWebhookEvent::query()
                    ->where('provider', $provider)
                    ->where('external_event_id', $event->eventId)
                    ->first();

                if ($existing === null) {
                    continue;
                }

                if ($existing->payload_hash !== $payloadHash) {
                    throw MalformedWebhookPayloadException::make('event id reused with a different payload.');
                }

                if ($existing->processed_at !== null) {
                    return false;
                }

                usleep(self::POLL_INTERVAL_MICROSECONDS);
            }
        }

        throw new RuntimeException('Could not claim refund webhook event after '.self::MAX_POLL_ATTEMPTS.' attempts.');
    }
}
