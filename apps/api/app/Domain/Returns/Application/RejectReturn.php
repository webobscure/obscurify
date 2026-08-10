<?php

namespace App\Domain\Returns\Application;

use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Models\ReturnEvent;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\Returns\Support\ReturnStateMachine;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;

final class RejectReturn
{
    public function __construct(
        private readonly ReturnStateMachine $stateMachine,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    public function handle(ReturnRequest $returnRequest, ?string $reason): ReturnRequest
    {
        return DB::transaction(function () use ($returnRequest, $reason) {
            $locked = ReturnRequest::query()->whereKey($returnRequest->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->guard($locked->status, ReturnStatus::Rejected);

            $locked->update([
                'status' => ReturnStatus::Rejected->value,
                'closed_at' => now(),
            ]);

            ReturnEvent::query()->create([
                'return_request_id' => $locked->id,
                'type' => 'rejected',
                'description' => $reason ?? 'Return rejected.',
                'occurred_at' => now(),
            ]);

            $this->recordOutboxEvent->handle('ReturnRejected', 'ReturnRequest', $locked->id, [
                'return_request_id' => $locked->id,
                'order_id' => $locked->order_id,
                'store_id' => $locked->store_id,
            ]);

            return $locked->fresh(['items', 'events']);
        });
    }
}
