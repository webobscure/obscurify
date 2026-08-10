<?php

namespace App\Domain\Financial\Application;

use App\Domain\Financial\Models\RefundNumberSequence;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Tenant-scoped, gap-free, concurrency-safe sequential refund numbers via
 * one locked counter row per store — mirrors AllocateOrderNumber/
 * AllocateReturnNumber exactly. Must be called from inside RequestRefund's
 * transaction so the increment and the Refund row it numbers commit or
 * roll back together.
 */
final class AllocateRefundNumber
{
    public function handle(string $storeId): int
    {
        $sequence = RefundNumberSequence::query()->whereKey($storeId)->lockForUpdate()->first();

        if ($sequence === null) {
            try {
                DB::transaction(function () use ($storeId) {
                    RefundNumberSequence::query()->create(['store_id' => $storeId, 'next_number' => 1001]);
                });
            } catch (UniqueConstraintViolationException) {
                // Someone else created it a moment ago — fall through and
                // re-select with a lock, which now blocks correctly.
            }

            $sequence = RefundNumberSequence::query()->whereKey($storeId)->lockForUpdate()->firstOrFail();
        }

        $number = $sequence->next_number;
        $sequence->update(['next_number' => $number + 1]);

        return $number;
    }
}
