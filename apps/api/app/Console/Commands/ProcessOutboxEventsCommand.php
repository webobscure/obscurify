<?php

namespace App\Console\Commands;

use App\Domain\Stores\Models\Store;
use App\Domain\Webhooks\Application\DispatchWebhooksForEvent;
use App\Shared\Commerce\Models\OutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The transactional outbox's asynchronous side (Order + OutboxEvent
 * commit together inside CompleteCheckout etc. — see RecordOutboxEvent
 * — this command is what turns "committed" into "acted on"). Milestone
 * 11 (Platform Events + Webhooks) added the one real subscriber: every
 * claimed event is fanned out to matching WebhookSubscriptions via
 * DispatchWebhooksForEvent, inside the same tenant scope and the same
 * row-locked transaction that marks the event processed — so a webhook
 * dispatch and the "processed" flag always commit or roll back together.
 */
class ProcessOutboxEventsCommand extends Command
{
    protected $signature = 'outbox:process';

    protected $description = 'Process unprocessed outbox events and dispatch matching webhooks';

    public function __construct(
        private readonly DispatchWebhooksForEvent $dispatchWebhooksForEvent,
        private readonly TenantContext $tenantContext,
    ) {
        parent::__construct();
    }

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

                $store = Store::query()->find($event->store_id);

                if ($store !== null) {
                    $this->tenantContext->scope($store, fn () => $this->dispatchWebhooksForEvent->handle($event));
                }

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
