<?php

namespace App\Domain\Payments\Application;

use App\Domain\Orders\Enums\FinancialStatus;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Enums\PaymentSessionStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentAttempt;
use App\Domain\Payments\Models\PaymentSession;
use App\Domain\Payments\Support\PaymentProviderRegistry;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single, transactional use case that initiates a payment against an
 * Order. Amount and currency are always read from the (locked) Order row
 * itself — never trusted from the caller — so this method's signature
 * deliberately takes no amount/currency parameters at all.
 *
 * Idempotency-Key handling is the caller's concern (see
 * StorefrontPaymentController), same division of responsibility as
 * CompleteCheckout/StorefrontCheckoutController in the Checkout module —
 * this class assumes it already holds the idempotency claim.
 */
final class CreatePayment
{
    public function __construct(
        private readonly PaymentProviderRegistry $registry,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    public function handle(Order $order, string $providerCode): Payment
    {
        return DB::transaction(function () use ($order, $providerCode) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->order_status === OrderStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'order' => 'This order is cancelled.',
                ]);
            }

            if ($lockedOrder->financial_status !== FinancialStatus::Pending) {
                throw ValidationException::withMessages([
                    'order' => 'This order is not awaiting payment.',
                ]);
            }

            if ($lockedOrder->total_amount <= 0) {
                throw ValidationException::withMessages([
                    'order' => 'This order has nothing to pay.',
                ]);
            }

            // Blocks a second payment attempt while one is already
            // in-flight or already succeeded — stricter than the letter
            // of "an existing successful Payment does not already exist"
            // (spec section 17), deliberately: allowing two simultaneously
            // `processing` payments for the same order risks both
            // eventually succeeding via their own webhooks. A `failed`/
            // `cancelled` payment does not block a retry.
            $blocking = Payment::query()
                ->where('order_id', $lockedOrder->id)
                ->whereIn('status', [
                    PaymentStatus::Processing->value,
                    PaymentStatus::Authorized->value,
                    PaymentStatus::Paid->value,
                    PaymentStatus::PartiallyRefunded->value,
                    PaymentStatus::Refunded->value,
                ])
                ->exists();

            if ($blocking) {
                throw ValidationException::withMessages([
                    'order' => 'A payment already exists for this order.',
                ]);
            }

            $provider = $this->registry->resolve($providerCode);

            $payment = Payment::query()->create([
                'order_id' => $lockedOrder->id,
                'provider' => $providerCode,
                'status' => PaymentStatus::Pending->value,
                'currency' => $lockedOrder->currency,
                'amount' => $lockedOrder->total_amount,
            ]);

            $session = PaymentSession::query()->create([
                'order_id' => $lockedOrder->id,
                'payment_id' => $payment->id,
                'provider' => $providerCode,
                'status' => PaymentSessionStatus::Pending->value,
            ]);

            $result = $provider->createPayment($payment);

            $payment->update([
                'external_payment_id' => $result->externalPaymentId,
                'status' => PaymentStatus::Processing->value,
            ]);

            $session->update([
                'redirect_url' => $result->redirectUrl,
            ]);

            PaymentAttempt::query()->create([
                'payment_id' => $payment->id,
                'payment_session_id' => $session->id,
                'provider' => $providerCode,
                'status' => PaymentAttemptStatus::Succeeded->value,
                'external_attempt_id' => $result->externalPaymentId,
            ]);

            $this->recordOutboxEvent->handle('PaymentCreated', 'Payment', $payment->id, [
                'payment_id' => $payment->id,
                'order_id' => $lockedOrder->id,
                'store_id' => $payment->store_id,
                'provider' => $providerCode,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ]);

            return $payment->fresh(['sessions', 'attempts']);
        });
    }
}
