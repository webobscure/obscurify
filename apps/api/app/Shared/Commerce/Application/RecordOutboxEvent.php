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
     * $causedByWorkflowExecutionId links an event recorded by an
     * automation action (e.g. "Publish event", or indirectly "Add
     * customer tag") back to the WorkflowExecution that produced it —
     * WorkflowLoopGuard reads this chain to detect a workflow that
     * (directly or transitively) triggers itself. Every other caller
     * omits it, exactly as before this parameter existed.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $eventType, string $aggregateType, string $aggregateId, array $payload, ?string $causedByWorkflowExecutionId = null): OutboxEvent
    {
        return OutboxEvent::query()->create([
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'payload' => $payload,
            'occurred_at' => now(),
            'caused_by_workflow_execution_id' => $causedByWorkflowExecutionId,
        ]);
    }
}
