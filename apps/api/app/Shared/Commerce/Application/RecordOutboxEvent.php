<?php

namespace App\Shared\Commerce\Application;

use App\Shared\Commerce\Models\OutboxEvent;

/**
 * Called from inside the same DB transaction as the change it describes
 * (CompleteCheckout, for OrderCreated) — that's what closes the
 * "committed but the event never gets written" window a message broker
 * alone can't guarantee. See ProcessOutboxEventsCommand for the
 * asynchronous side.
 */
final class RecordOutboxEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $eventType, string $aggregateType, string $aggregateId, array $payload): OutboxEvent
    {
        return OutboxEvent::query()->create([
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }
}
