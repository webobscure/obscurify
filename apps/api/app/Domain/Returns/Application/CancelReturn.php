<?php

namespace App\Domain\Returns\Application;

use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Models\ReturnEvent;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\Returns\Support\ReturnStateMachine;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;

/**
 * Not enumerated in spec section 13's endpoint list, but `cancelled` is
 * one of the required statuses (section 3) and every sibling domain
 * (Fulfillment, Shipment) exposes a dedicated cancel action rather than a
 * generic status write through PATCH — added for the same reason:
 * cancellation must go through the state machine, not a bare field
 * update.
 */
final class CancelReturn
{
    public function __construct(
        private readonly ReturnStateMachine $stateMachine,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    public function handle(ReturnRequest $returnRequest): ReturnRequest
    {
        return DB::transaction(function () use ($returnRequest) {
            $locked = ReturnRequest::query()->whereKey($returnRequest->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->guard($locked->status, ReturnStatus::Cancelled);

            $locked->update([
                'status' => ReturnStatus::Cancelled->value,
                'closed_at' => now(),
            ]);

            ReturnEvent::query()->create([
                'return_request_id' => $locked->id,
                'type' => 'cancelled',
                'description' => 'Return cancelled.',
                'occurred_at' => now(),
            ]);

            $this->recordOutboxEvent->handle('ReturnCancelled', 'ReturnRequest', $locked->id, [
                'return_request_id' => $locked->id,
                'order_id' => $locked->order_id,
                'store_id' => $locked->store_id,
            ]);

            return $locked->fresh(['items', 'events']);
        });
    }
}
