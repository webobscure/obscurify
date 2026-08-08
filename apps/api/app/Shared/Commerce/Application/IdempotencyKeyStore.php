<?php

namespace App\Shared\Commerce\Application;

use App\Shared\Commerce\Exceptions\IdempotencyConflictException;
use App\Shared\Commerce\Models\IdempotencyKey;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Makes any operation safe to retry with the same Idempotency-Key,
 * including when two identical requests arrive genuinely concurrently.
 *
 * The (store_id, operation, key) unique index on idempotency_keys is the
 * actual concurrency primitive, not an in-PHP lock:
 *
 *  1. claimOrReplay() tries to INSERT a placeholder row (response_status
 *     still null). Whichever of two concurrent requests wins the insert
 *     proceeds to run $callback.
 *  2. The loser hits a unique violation and instead polls the winner's
 *     row in awaitExisting() until response_status is filled in (success:
 *     replay it) or the row disappears (failure: the winner rolled back
 *     or deleted it — the slot is free again, retry the insert).
 *
 * Polling, not `SELECT ... FOR UPDATE`, is deliberate: the row lock the
 * winner takes only lives for the brief, separately-committed INSERT
 * transaction in claimOrReplay() — by the time a concurrent loser can see
 * the row at all, that lock is already released, and the winner is off
 * running $callback (its own, separate transaction(s)) and then a bare,
 * autocommitted UPDATE to persist the result. Nothing holds a lock across
 * that whole window, so a `SELECT ... FOR UPDATE` there would return
 * immediately with response_status still null instead of actually
 * waiting — which is exactly the bug polling avoids.
 *
 * $callback must not send any external HTTP request (no payment provider
 * calls exist yet anyway) and should return the final
 * {status, body} to persist and hand back on replay.
 */
final class IdempotencyKeyStore
{
    private const MAX_ATTEMPTS = 5;

    private const MAX_POLL_ATTEMPTS = 100;

    private const POLL_INTERVAL_MICROSECONDS = 20_000;

    /**
     * @param  callable(): array{status: int, body: array<string, mixed>}  $callback
     * @return array{status: int, body: array<string, mixed>}
     */
    public function handle(string $operation, string $key, ?string $requestHash, callable $callback): array
    {
        $replay = $this->claimOrReplay($operation, $key, $requestHash);

        if ($replay !== null) {
            return $replay;
        }

        // We definitively hold the claim at this point.
        try {
            $result = $callback();
        } catch (Throwable $e) {
            // Free the slot so a genuine retry with the same key can
            // still succeed later.
            IdempotencyKey::query()
                ->where('operation', $operation)
                ->where('key', $key)
                ->delete();

            throw $e;
        }

        IdempotencyKey::query()
            ->where('operation', $operation)
            ->where('key', $key)
            ->update([
                'response_status' => $result['status'],
                'response_body' => $result['body'],
            ]);

        return $result;
    }

    /**
     * @return array{status: int, body: array<string, mixed>}|null null
     *                                                             means the claim insert succeeded — the caller now holds it
     *                                                             and must run $callback. A non-null return is a completed
     *                                                             response to replay instead.
     */
    private function claimOrReplay(string $operation, string $key, ?string $requestHash): ?array
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            try {
                // Wrapped so a caught unique-violation only rolls back to
                // a savepoint, not the ambient transaction (there always
                // is one under Pest's RefreshDatabase, and there may be
                // one in production if a caller is already inside a
                // transaction) — an uncaught exception from a bare
                // ::create() call would otherwise abort that whole
                // transaction under Postgres, poisoning every query after
                // it until an explicit rollback.
                DB::transaction(function () use ($operation, $key, $requestHash) {
                    IdempotencyKey::query()->create([
                        'operation' => $operation,
                        'key' => $key,
                        'request_hash' => $requestHash,
                        'expires_at' => now()->addHours(24),
                    ]);
                });

                return null;
            } catch (UniqueConstraintViolationException) {
                $replay = $this->awaitExisting($operation, $key, $requestHash);

                if ($replay !== null) {
                    return $replay;
                }

                // The row that caused the conflict is gone (its owner
                // rolled back) — the slot is free again, loop and retry
                // the insert.
            }
        }

        throw new RuntimeException('Could not claim idempotency key after '.self::MAX_ATTEMPTS.' attempts.');
    }

    /**
     * @return array{status: int, body: array<string, mixed>}|null null
     *                                                             means the other holder's row is gone (rolled back or
     *                                                             deleted after a failure) and freed the slot — the caller
     *                                                             should retry the insert.
     */
    private function awaitExisting(string $operation, string $key, ?string $requestHash): ?array
    {
        for ($poll = 0; $poll < self::MAX_POLL_ATTEMPTS; $poll++) {
            $row = IdempotencyKey::query()
                ->where('operation', $operation)
                ->where('key', $key)
                ->first();

            if ($row === null) {
                return null;
            }

            if ($requestHash !== null && $row->request_hash !== null && $row->request_hash !== $requestHash) {
                throw IdempotencyConflictException::make();
            }

            if ($row->response_status !== null) {
                return ['status' => $row->response_status, 'body' => $row->response_body];
            }

            // Still in flight: the winner has claimed the slot but hasn't
            // finished $callback and persisted a result yet. Poll rather
            // than block — see class docblock for why a lock can't do
            // this here.
            usleep(self::POLL_INTERVAL_MICROSECONDS);
        }

        throw IdempotencyConflictException::make();
    }
}
