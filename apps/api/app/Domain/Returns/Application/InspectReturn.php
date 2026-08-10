<?php

namespace App\Domain\Returns\Application;

use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Models\ReturnDisposition;
use App\Domain\Returns\Models\ReturnEvent;
use App\Domain\Returns\Models\ReturnInspection;
use App\Domain\Returns\Models\ReturnItem;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\Returns\Support\ReturnStateMachine;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records the verified inspection *and* the disposition decision for
 * every item in one call (spec sections 6/7) — spec section 13 lists a
 * single `/inspect` endpoint, no separate disposition endpoint, so a
 * merchant examines an item and immediately decides what happens to it
 * in the same form submission. The disposition is only *applied* to
 * Inventory later, at CompleteReturn (spec section 8: inventory changes
 * happen only after inspection — "after," not "as part of," so applying
 * it at Complete rather than here keeps that boundary literal).
 */
final class InspectReturn
{
    public function __construct(
        private readonly ReturnStateMachine $stateMachine,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    /**
     * @param  list<array{return_item_id: string, condition: string, photos?: array<int, mixed>|null, notes?: string|null, disposition: string, disposition_notes?: string|null}>  $items
     */
    public function handle(ReturnRequest $returnRequest, array $items, ?string $inspectedBy): ReturnRequest
    {
        return DB::transaction(function () use ($returnRequest, $items, $inspectedBy) {
            $locked = ReturnRequest::query()->whereKey($returnRequest->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->guard($locked->status, ReturnStatus::Inspection);

            foreach ($items as $line) {
                $item = ReturnItem::query()
                    ->where('return_request_id', $locked->id)
                    ->whereKey($line['return_item_id'])
                    ->lockForUpdate()
                    ->first();

                if ($item === null) {
                    throw ValidationException::withMessages([
                        'items' => "Return item \"{$line['return_item_id']}\" does not belong to this return.",
                    ]);
                }

                if (ReturnInspection::query()->where('return_item_id', $item->id)->exists()) {
                    throw ValidationException::withMessages([
                        'items' => "Return item \"{$item->id}\" has already been inspected.",
                    ]);
                }

                ReturnInspection::query()->create([
                    'return_item_id' => $item->id,
                    'condition' => $line['condition'],
                    'photos' => $line['photos'] ?? null,
                    'notes' => $line['notes'] ?? null,
                    'inspected_by' => $inspectedBy,
                    'inspected_at' => now(),
                ]);

                ReturnDisposition::query()->create([
                    'return_item_id' => $item->id,
                    'disposition' => $line['disposition'],
                    'notes' => $line['disposition_notes'] ?? null,
                    'decided_by' => $inspectedBy,
                    'decided_at' => now(),
                ]);
            }

            $locked->update(['status' => ReturnStatus::Inspection->value]);

            ReturnEvent::query()->create([
                'return_request_id' => $locked->id,
                'type' => 'inspection_completed',
                'description' => 'Inspection completed.',
                'occurred_at' => now(),
            ]);

            $this->recordOutboxEvent->handle('ReturnInspected', 'ReturnRequest', $locked->id, [
                'return_request_id' => $locked->id,
                'order_id' => $locked->order_id,
                'store_id' => $locked->store_id,
            ]);

            return $locked->fresh(['items.inspection', 'items.disposition', 'events']);
        });
    }
}
