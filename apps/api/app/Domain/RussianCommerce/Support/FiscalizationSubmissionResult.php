<?php

namespace App\Domain\RussianCommerce\Support;

/**
 * What a provider's submitReceipt() hands back immediately — never a
 * final fiscalized/failed outcome (real OFD providers confirm
 * asynchronously; see FiscalizationProviderContract).
 */
final readonly class FiscalizationSubmissionResult
{
    public function __construct(
        public string $externalReceiptId,
    ) {}
}
