<?php

namespace App\Domain\Orders\Application;

use App\Domain\Orders\Models\OrderNumberSequence;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Tenant-scoped, gap-free, concurrency-safe sequential order numbers via
 * one locked counter row per store — not a global DB sequence (can't be
 * reset/scoped per tenant) and not a racy `MAX(number)+1` on the orders
 * table itself.
 *
 * Must be called from inside CompleteCheckout's transaction so the
 * increment and the Order row it numbers commit or roll back together.
 */
final class AllocateOrderNumber
{
    public function handle(string $storeId): int
    {
        $sequence = OrderNumberSequence::query()->whereKey($storeId)->lockForUpdate()->first();

        if ($sequence === null) {
            try {
                // Wrapped for savepoint protection: a caught unique
                // violation here (another concurrent first-order race)
                // must not poison the caller's ambient transaction — see
                // IdempotencyKeyStore's claimOrReplay() for the same
                // pattern.
                DB::transaction(function () use ($storeId) {
                    OrderNumberSequence::query()->create(['store_id' => $storeId, 'next_number' => 1001]);
                });
            } catch (UniqueConstraintViolationException) {
                // Someone else created it a moment ago — fall through and
                // re-select with a lock, which now blocks correctly.
            }

            $sequence = OrderNumberSequence::query()->whereKey($storeId)->lockForUpdate()->firstOrFail();
        }

        $number = $sequence->next_number;
        $sequence->update(['next_number' => $number + 1]);

        return $number;
    }
}
