<?php

namespace App\Domain\RussianCommerce\Support;

/**
 * Spec section 9 — architecture only, no real QR generation or SBP
 * (Система Быстрых Платежей) provider calls. Stored inside
 * `Payment.method_metadata` when `Payment.payment_method === sbp`.
 * `qrPayload`/`deeplinkUrl`/`providerConfirmationUrl` are all nullable
 * because nothing populates them yet — a future real SBP integration
 * fills these in from the provider's own initiation response, using
 * this exact shape so nothing else in the codebase needs to change.
 */
final readonly class SbpPaymentMetadata
{
    public function __construct(
        public ?string $qrPayload = null,
        public ?string $deeplinkUrl = null,
        public ?string $providerConfirmationUrl = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            qrPayload: $data['qr_payload'] ?? null,
            deeplinkUrl: $data['deeplink_url'] ?? null,
            providerConfirmationUrl: $data['provider_confirmation_url'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'sbp',
            'qr_payload' => $this->qrPayload,
            'deeplink_url' => $this->deeplinkUrl,
            'provider_confirmation_url' => $this->providerConfirmationUrl,
        ];
    }
}
