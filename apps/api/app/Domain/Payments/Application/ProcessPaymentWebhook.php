<?php

namespace App\Domain\Payments\Application;

use App\Domain\Financial\Application\RecomputeOrderFinancialStatus;
use App\Domain\Financial\Application\RecordPaymentCapturedLedgerEntries;
use App\Domain\Orders\Enums\FinancialStatus;
use App\Domain\Payments\Enums\PaymentSessionStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\PaymentTransactionStatus;
use App\Domain\Payments\Enums\PaymentTransactionType;
use App\Domain\Payments\Exceptions\MalformedWebhookPayloadException;
use App\Domain\Payments\Exceptions\UnknownPaymentException;
use App\Domain\Payments\Exceptions\WebhookReplayException;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentSession;
use App\Domain\Payments\Models\PaymentTransaction;
use App\Domain\Payments\Models\PaymentWebhookEvent;
use App\Domain\Payments\Support\PaymentStateMachine;
use App\Domain\Payments\Support\WebhookEvent;
use App\Domain\Stores\Models\Store;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The provider-neutral webhook handler — see StorefrontPaymentController/
 * PaymentWebhookController for where verifyWebhook()/parseWebhook() are
 * called before this; by the time `handle()` runs, the signature is
 * already verified and the payload already parsed into a WebhookEvent.
 *
 * Tenant resolution problem this solves (spec section 24): a webhook
 * carries no TenantContext. This method resolves store_id from the
 * (provider, external_payment_id) → Payment mapping — never from
 * anything in the webhook payload itself — before entering
 * TenantContext::scope() to do the actual, tenant-scoped work.
 *
 * Idempotency (spec section 14) is its own claim/poll protocol against
 * payment_webhook_events.(provider, external_event_id), deliberately not
 * the tenant-scoped idempotency_keys table used for payment creation —
 * see that migration's docblock for why a tenant-scoped table can't do
 * this job.
 */
final class ProcessPaymentWebhook
{
    private const MAX_POLL_ATTEMPTS = 100;

    private const POLL_INTERVAL_MICROSECONDS = 20_000;

    public function __construct(
        private readonly PaymentStateMachine $stateMachine,
        private readonly RecordOutboxEvent $recordOutboxEvent,
        private readonly RecordPaymentCapturedLedgerEntries $recordPaymentCapturedLedgerEntries,
        private readonly RecomputeOrderFinancialStatus $recomputeOrderFinancialStatus,
    ) {}

    public function handle(string $providerCode, WebhookEvent $event, string $rawPayload): void
    {
        $tolerance = (int) config('payments.webhook.replay_tolerance_seconds');

        if ($event->occurredAt->lt(now()->subSeconds($tolerance))) {
            throw WebhookReplayException::make();
        }

        $payloadHash = hash('sha256', $rawPayload);

        $claimed = $this->claimOrAlreadyProcessed($providerCode, $event, $payloadHash);

        if (! $claimed) {
            // Already processed by another (possibly concurrent) delivery
            // of the exact same event — idempotent no-op, nothing to do.
            return;
        }

        $payment = Payment::withoutGlobalScopes()
            ->where('provider', $providerCode)
            ->where('external_payment_id', $event->externalPaymentId)
            ->first();

        if ($payment === null) {
            PaymentWebhookEvent::query()
                ->where('provider', $providerCode)
                ->where('external_event_id', $event->eventId)
                ->update(['processed_at' => now()]);

            throw UnknownPaymentException::forExternalId($providerCode, $event->externalPaymentId);
        }

        $store = Store::query()->findOrFail($payment->store_id);

        app(TenantContext::class)->scope($store, function () use ($providerCode, $event, $payment, $store) {
            DB::transaction(function () use ($providerCode, $event, $payment, $store) {
                $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

                [$transactionType, $transactionStatus, $target] = $this->mapEventToTransition($event, $lockedPayment->status);

                PaymentTransaction::query()->create([
                    'payment_id' => $lockedPayment->id,
                    'type' => $transactionType->value,
                    'status' => $transactionStatus->value,
                    'currency' => $event->currency,
                    'amount' => $event->amount,
                    'external_transaction_id' => $event->eventId,
                ]);

                if ($target !== null && $target !== $lockedPayment->status) {
                    if (! $this->stateMachine->canTransition($lockedPayment->status, $target)) {
                        // Out-of-order or redundant delivery (e.g. a
                        // "cancelled" webhook arriving after the payment
                        // already reached a terminal "paid" state from a
                        // different event) — the transaction row above
                        // still records that we saw it; the Payment's own
                        // status is left alone rather than erroring.
                        Log::warning('payments.webhook.invalid_transition', [
                            'payment_id' => $lockedPayment->id,
                            'from' => $lockedPayment->status->value,
                            'to' => $target->value,
                        ]);
                    } else {
                        $this->applyTransition($lockedPayment, $target, $event);
                    }
                }

                PaymentWebhookEvent::query()
                    ->where('provider', $providerCode)
                    ->where('external_event_id', $event->eventId)
                    ->update(['store_id' => $store->id, 'processed_at' => now()]);
            });
        });
    }

    /**
     * @return array{0: PaymentTransactionType, 1: PaymentTransactionStatus, 2: PaymentStatus|null}
     */
    private function mapEventToTransition(WebhookEvent $event, PaymentStatus $current): array
    {
        return match ($event->status) {
            'succeeded' => [PaymentTransactionType::Payment, PaymentTransactionStatus::Succeeded, PaymentStatus::Paid],
            'failed' => [PaymentTransactionType::Payment, PaymentTransactionStatus::Failed, PaymentStatus::Failed],
            'cancelled' => [PaymentTransactionType::Cancel, PaymentTransactionStatus::Succeeded, PaymentStatus::Cancelled],
            'pending' => [PaymentTransactionType::Webhook, PaymentTransactionStatus::Pending, null],
            default => throw MalformedWebhookPayloadException::make("unrecognized status \"{$event->status}\"."),
        };
    }

    private function applyTransition(Payment $payment, PaymentStatus $target, WebhookEvent $event): void
    {
        $updates = ['status' => $target->value];

        if ($target === PaymentStatus::Paid) {
            $updates['captured_amount'] = $event->amount;
        }

        $payment->update($updates);

        if (in_array($target, [PaymentStatus::Paid, PaymentStatus::Failed, PaymentStatus::Cancelled], true)) {
            PaymentSession::query()->where('payment_id', $payment->id)->update([
                'status' => PaymentSessionStatus::Completed->value,
            ]);
        }

        if ($target === PaymentStatus::Paid) {
            // Order financial_status only ever moves here (on first
            // capture) or from Financial\Application\ApplyRefundCompletion
            // (on refund) — never from payment creation, a redirect-return
            // page, or any other path (spec section 19). Both callers
            // funnel through RecomputeOrderFinancialStatus, which derives
            // the status from scratch rather than writing it directly
            // (spec section 6).
            $order = $payment->order()->lockForUpdate()->first();

            if ($order !== null) {
                $wasPending = $order->financial_status === FinancialStatus::Pending;

                // Ledger entries for a capture are posted exactly once,
                // the first time this Payment reaches Paid — a later
                // duplicate/out-of-order "succeeded" webhook for the same
                // Payment can never reach this branch a second time,
                // since $target !== $lockedPayment->status already
                // guards the caller above (same-state is a no-op).
                if ($wasPending) {
                    $this->recordPaymentCapturedLedgerEntries->handle($order, $payment);

                    $this->recordOutboxEvent->handle('OrderPaymentConfirmed', 'Order', $order->id, [
                        'order_id' => $order->id,
                        'payment_id' => $payment->id,
                        'store_id' => $payment->store_id,
                    ]);
                }

                $this->recomputeOrderFinancialStatus->handle($order);
            }
        }

        $eventType = match ($target) {
            PaymentStatus::Paid => 'PaymentPaid',
            PaymentStatus::Failed => 'PaymentFailed',
            PaymentStatus::Cancelled => 'PaymentCancelled',
            default => throw new RuntimeException("No outbox event mapped for payment status \"{$target->value}\"."),
        };

        $this->recordOutboxEvent->handle($eventType, 'Payment', $payment->id, [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'store_id' => $payment->store_id,
            'status' => $target->value,
            'amount' => $event->amount,
            'currency' => $event->currency,
        ]);
    }

    /**
     * @return bool true if this call holds the claim and must process the
     *              event; false if another delivery already fully
     *              processed it (idempotent no-op).
     */
    private function claimOrAlreadyProcessed(string $provider, WebhookEvent $event, string $payloadHash): bool
    {
        for ($attempt = 0; $attempt < self::MAX_POLL_ATTEMPTS; $attempt++) {
            try {
                DB::transaction(function () use ($provider, $event, $payloadHash) {
                    PaymentWebhookEvent::query()->create([
                        'provider' => $provider,
                        'external_event_id' => $event->eventId,
                        'external_payment_id' => $event->externalPaymentId,
                        'event_type' => $event->eventType,
                        'payload_hash' => $payloadHash,
                    ]);
                });

                return true;
            } catch (UniqueConstraintViolationException) {
                $existing = PaymentWebhookEvent::query()
                    ->where('provider', $provider)
                    ->where('external_event_id', $event->eventId)
                    ->first();

                if ($existing === null) {
                    // The other holder rolled back / the row is gone —
                    // the slot is free again, retry the claim.
                    continue;
                }

                if ($existing->payload_hash !== $payloadHash) {
                    throw MalformedWebhookPayloadException::make('event id reused with a different payload.');
                }

                if ($existing->processed_at !== null) {
                    return false;
                }

                // Still in flight elsewhere — poll rather than block, same
                // reasoning as IdempotencyKeyStore::awaitExisting().
                usleep(self::POLL_INTERVAL_MICROSECONDS);
            }
        }

        throw new RuntimeException('Could not claim webhook event after '.self::MAX_POLL_ATTEMPTS.' attempts.');
    }
}
