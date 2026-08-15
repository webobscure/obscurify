<?php

namespace App\Domain\Notifications\Support;

/**
 * The one shape every NotificationProviderContract::send() call
 * returns, success or failure — analogous to Payments'
 * PaymentInitiationResult, but for a synchronous send/fail outcome
 * rather than an async redirect-then-webhook flow (no real provider
 * exists yet to need the async shape — see docs/adr/027-notification-center.md).
 */
final readonly class NotificationSendResult
{
    private function __construct(
        public bool $success,
        public ?string $externalId,
        public array $responseMeta,
        public ?string $errorMessage,
    ) {}

    public static function success(?string $externalId = null, array $responseMeta = []): self
    {
        return new self(true, $externalId, $responseMeta, null);
    }

    public static function failure(string $errorMessage, array $responseMeta = []): self
    {
        return new self(false, null, $responseMeta, $errorMessage);
    }
}
