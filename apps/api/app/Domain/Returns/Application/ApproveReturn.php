<?php

namespace App\Domain\Returns\Application;

use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Models\ReturnEvent;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\Returns\Support\ReturnStateMachine;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;

/**
 * Merchant approves a return in principle. Immediately advances to
 * `awaiting_return` in the same call (see ReturnStateMachine's docblock)
 * — there's nothing further to decide between "approved" and "now we
 * wait for the physical package," and spec section 13 only lists one
 * `/approve` endpoint.
 */
final class ApproveReturn
{
    public function __construct(
        private readonly ReturnStateMachine $stateMachine,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    public function handle(ReturnRequest $returnRequest): ReturnRequest
    {
        return DB::transaction(function () use ($returnRequest) {
            $locked = ReturnRequest::query()->whereKey($returnRequest->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->guard($locked->status, ReturnStatus::Approved);

            $locked->update([
                'status' => ReturnStatus::Approved->value,
                'approved_at' => now(),
            ]);

            ReturnEvent::query()->create([
                'return_request_id' => $locked->id,
                'type' => 'approved',
                'description' => 'Return approved.',
                'occurred_at' => now(),
            ]);

            $this->stateMachine->guard(ReturnStatus::Approved, ReturnStatus::AwaitingReturn);

            $locked->update(['status' => ReturnStatus::AwaitingReturn->value]);

            ReturnEvent::query()->create([
                'return_request_id' => $locked->id,
                'type' => 'awaiting_return',
                'description' => 'Awaiting the customer to ship the item back.',
                'occurred_at' => now(),
            ]);

            $this->recordOutboxEvent->handle('ReturnApproved', 'ReturnRequest', $locked->id, [
                'return_request_id' => $locked->id,
                'order_id' => $locked->order_id,
                'store_id' => $locked->store_id,
            ]);

            return $locked->fresh(['items', 'events']);
        });
    }
}
