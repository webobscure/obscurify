<?php

namespace App\Domain\RussianCommerce\Support;

/**
 * A parsed, verified provider callback — the async confirmation half of
 * fiscalization (mirrors Payments' WebhookEvent). `succeeded: false`
 * carries `errorMessage` for FiscalReceipt.error_message.
 */
final readonly class FiscalizationCallbackEvent
{
    public function __construct(
        public string $externalReceiptId,
        public bool $succeeded,
        public ?string $errorMessage = null,
    ) {}
}
