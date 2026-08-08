<?php

namespace App\Domain\Payments\Support;

use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Exceptions\InvalidPaymentTransitionException;

/**
 * The only place Payment status transitions are allowed to happen from.
 * `failed -> paid` (an asynchronous provider correction) is deliberately
 * NOT supported this milestone — see spec section 20 — a payment that
 * has failed stays failed; a genuine retry always creates a new Payment
 * (see CreatePayment).
 *
 * `processing -> cancelled` is a deliberate addition beyond the spec's
 * minimum list: a customer can cancel while still on the (fake) payment
 * page, which is the `processing` state, not `pending`.
 */
final class PaymentStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const array TRANSITIONS = [
        'pending' => ['processing', 'failed', 'cancelled'],
        'processing' => ['authorized', 'paid', 'failed', 'cancelled'],
        'authorized' => ['paid', 'failed', 'cancelled'],
        'paid' => ['partially_refunded', 'refunded'],
        'partially_refunded' => ['refunded'],
    ];

    public function canTransition(PaymentStatus $from, PaymentStatus $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    /**
     * @throws InvalidPaymentTransitionException
     */
    public function guard(PaymentStatus $from, PaymentStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw InvalidPaymentTransitionException::make($from, $to);
        }
    }
}
