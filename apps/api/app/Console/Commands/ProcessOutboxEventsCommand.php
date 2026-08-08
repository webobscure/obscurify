<?php

namespace App\Console\Commands;

use App\Shared\Commerce\Models\OutboxEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Deliberately minimal: no email/payment/webhook side effects (out of
 * scope this milestone — see commerce spec section 45). This exists to
 * prove the transactional-outbox atomicity guarantee end to end (Order +
 * OutboxEvent commit together, see RecordOutboxEvent), with a real
 * processing loop rather than leaving the table to just accumulate rows.
 */
class ProcessOutboxEventsCommand extends Command
{
    protected $signature = 'outbox:process';

    protected $description = 'Process unprocessed outbox events';

    public function handle(): int
    {
        $ids = OutboxEvent::withoutGlobalScopes()
            ->whereNull('processed_at')
            ->orderBy('occurred_at')
            ->limit(100)
            ->pluck('id');

        $processed = 0;

        foreach ($ids as $id) {
            $didProcess = DB::transaction(function () use ($id) {
                $event = OutboxEvent::withoutGlobalScopes()->whereKey($id)->lockForUpdate()->first();

                if ($event === null || $event->processed_at !== null) {
                    return false;
                }

                Log::info('outbox.processed', [
                    'event_type' => $event->event_type,
                    'aggregate_type' => $event->aggregate_type,
                    'aggregate_id' => $event->aggregate_id,
                ]);

                $event->update([
                    'processed_at' => now(),
                    'attempts' => $event->attempts + 1,
                ]);

                return true;
            });

            if ($didProcess) {
                $processed++;
            }
        }

        $this->info("Processed {$processed} outbox event(s).");

        return self::SUCCESS;
    }
}
