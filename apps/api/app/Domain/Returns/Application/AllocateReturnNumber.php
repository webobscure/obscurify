<?php

namespace App\Domain\Returns\Application;

use App\Domain\Returns\Models\ReturnNumberSequence;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Tenant-scoped, gap-free, concurrency-safe sequential return numbers via
 * one locked counter row per store — mirrors AllocateOrderNumber exactly.
 * Must be called from inside RequestReturn's transaction so the increment
 * and the ReturnRequest row it numbers commit or roll back together.
 */
final class AllocateReturnNumber
{
    public function handle(string $storeId): int
    {
        $sequence = ReturnNumberSequence::query()->whereKey($storeId)->lockForUpdate()->first();

        if ($sequence === null) {
            try {
                // Wrapped for savepoint protection: a caught unique
                // violation here (another concurrent first-return race)
                // must not poison the caller's ambient transaction — see
                // AllocateOrderNumber for the identical reasoning.
                DB::transaction(function () use ($storeId) {
                    ReturnNumberSequence::query()->create(['store_id' => $storeId, 'next_number' => 1001]);
                });
            } catch (UniqueConstraintViolationException) {
                // Someone else created it a moment ago — fall through and
                // re-select with a lock, which now blocks correctly.
            }

            $sequence = ReturnNumberSequence::query()->whereKey($storeId)->lockForUpdate()->firstOrFail();
        }

        $number = $sequence->next_number;
        $sequence->update(['next_number' => $number + 1]);

        return $number;
    }
}
