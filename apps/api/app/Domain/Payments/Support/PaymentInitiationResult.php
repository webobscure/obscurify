<?php

namespace App\Domain\Payments\Support;

/**
 * What a provider hands back from createPayment(): enough to redirect the
 * customer and to remember which external payment this maps to. Nothing
 * provider-specific leaks past this shape into CreatePayment.
 */
final readonly class PaymentInitiationResult
{
    public function __construct(
        public string $externalPaymentId,
        public string $redirectUrl,
    ) {}
}
