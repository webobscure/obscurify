<?php

namespace App\Domain\Fulfillment\Application;

use App\Domain\Fulfillment\Enums\FulfillmentStatus;
use App\Domain\Fulfillment\Models\Fulfillment;
use App\Domain\Fulfillment\Models\FulfillmentEvent;
use App\Domain\Fulfillment\Models\FulfillmentItem;
use App\Domain\Fulfillment\Support\FulfillmentStateMachine;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records picking progress (spec section 9). The first call against a
 * `pending`-allocated Fulfillment transitions it to `picking` — an
 * "allocated" Fulfillment cannot be picked until AllocateFulfillment has
 * run, per the state machine, so allocate must always precede pick
 * (spec section 3: "do not skip intermediate warehouse states"). Partial
 * picks are allowed and expected: a merchant may call this endpoint
 * multiple times as items are physically picked off shelves.
 */
final class PickFulfillmentItems
{
    public function __construct(
        private readonly FulfillmentStateMachine $stateMachine,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    /**
     * @param  list<array{fulfillment_item_id: string, picked_quantity: int}>  $items
     */
    public function handle(Fulfillment $fulfillment, array $items): Fulfillment
    {
        return DB::transaction(function () use ($fulfillment, $items) {
            $locked = Fulfillment::query()->whereKey($fulfillment->id)->lockForUpdate()->firstOrFail();

            $wasPicking = $locked->status === FulfillmentStatus::Picking;

            if (! $wasPicking) {
                $this->stateMachine->guard($locked->status, FulfillmentStatus::Picking);
            }

            foreach ($items as $line) {
                $item = FulfillmentItem::query()
                    ->where('fulfillment_id', $locked->id)
                    ->whereKey($line['fulfillment_item_id'])
                    ->lockForUpdate()
                    ->first();

                if ($item === null) {
                    throw ValidationException::withMessages([
                        'items' => "Fulfillment item \"{$line['fulfillment_item_id']}\" does not belong to this fulfillment.",
                    ]);
                }

                if ($line['picked_quantity'] > $item->quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Picked quantity for \"{$item->id}\" cannot exceed the fulfillment item's quantity.",
                    ]);
                }

                $item->update(['picked_quantity' => $line['picked_quantity']]);
            }

            if (! $wasPicking) {
                $locked->update(['status' => FulfillmentStatus::Picking->value]);

                FulfillmentEvent::query()->create([
                    'fulfillment_id' => $locked->id,
                    'type' => 'picking_started',
                    'description' => 'Picking started.',
                    'occurred_at' => now(),
                ]);

                $this->recordOutboxEvent->handle('PickingStarted', 'Fulfillment', $locked->id, [
                    'fulfillment_id' => $locked->id,
                    'order_id' => $locked->order_id,
                    'store_id' => $locked->store_id,
                ]);
            }

            return $locked->fresh(['items', 'events']);
        });
    }
}
