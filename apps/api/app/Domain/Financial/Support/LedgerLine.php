<?php

namespace App\Domain\Financial\Support;

use App\Domain\Financial\Enums\LedgerAccount;
use App\Domain\Financial\Enums\LedgerDirection;

/**
 * One not-yet-persisted debit/credit line, passed into PostLedgerEntries.
 */
final readonly class LedgerLine
{
    public function __construct(
        public LedgerAccount $account,
        public LedgerDirection $direction,
        public int $amount,
    ) {}
}
