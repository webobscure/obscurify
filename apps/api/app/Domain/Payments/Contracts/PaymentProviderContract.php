<?php

namespace App\Domain\Payments\Contracts;

use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Support\PaymentInitiationResult;
use App\Domain\Payments\Support\WebhookEvent;
use Illuminate\Http\Request;

/**
 * Provider-neutral payment gateway boundary. Deliberately not modeled
 * around any one real provider's naming (e.g. YooKassa's own API shape) —
 * only the operations every provider we plan to integrate needs are
 * here; do not add speculative methods ahead of a concrete provider that
 * needs them.
 */
interface PaymentProviderContract
{
    /**
     * Registry key, e.g. "fake", "yookassa".
     */
    public function code(): string;

    /**
     * Initiates a payment with the provider. Must not mark the Payment as
     * paid — that only ever happens through a verified webhook.
     */
    public function createPayment(Payment $payment): PaymentInitiationResult;

    public function capturePayment(Payment $payment, ?int $amount = null): void;

    public function cancelPayment(Payment $payment): void;

    public function refundPayment(Payment $payment, int $amount): void;

    /**
     * Constant-time signature check against the raw request. Must be
     * called, and must pass, before parseWebhook() is trusted.
     */
    public function verifyWebhook(Request $request): bool;

    public function parseWebhook(Request $request): WebhookEvent;
}
