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
 * Records packing progress (spec section 10): confirms correct quantity,
 * correct items, package prepared — never marks the Fulfillment shipped.
 * The first call transitions `picking` -> `packing`. Once every
 * FulfillmentItem's packed_quantity reaches its quantity, this
 * automatically advances to `ready` (spec section 9's Packing -> Ready
 * step) — there is deliberately no separate "mark ready" admin endpoint
 * (see spec section 15's endpoint list), since "ready" isn't a merchant
 * decision, it's a fact that becomes true once packing is complete.
 */
final class PackFulfillmentItems
{
    public function __construct(
        private readonly FulfillmentStateMachine $stateMachine,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    /**
     * @param  list<array{fulfillment_item_id: string, packed_quantity: int}>  $items
     */
    public function handle(Fulfillment $fulfillment, array $items): Fulfillment
    {
        return DB::transaction(function () use ($fulfillment, $items) {
            $locked = Fulfillment::query()->whereKey($fulfillment->id)->lockForUpdate()->firstOrFail();

            $wasPacking = $locked->status === FulfillmentStatus::Packing;

            if (! $wasPacking) {
                $this->stateMachine->guard($locked->status, FulfillmentStatus::Packing);
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

                if ($line['packed_quantity'] > $item->picked_quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Packed quantity for \"{$item->id}\" cannot exceed its picked quantity.",
                    ]);
                }

                $item->update(['packed_quantity' => $line['packed_quantity']]);
            }

            if (! $wasPacking) {
                $locked->update(['status' => FulfillmentStatus::Packing->value]);

                FulfillmentEvent::query()->create([
                    'fulfillment_id' => $locked->id,
                    'type' => 'packing_started',
                    'description' => 'Packing started.',
                    'occurred_at' => now(),
                ]);
            }

            $allPacked = $locked->items()
                ->whereColumn('packed_quantity', '<', 'quantity')
                ->doesntExist();

            if ($allPacked) {
                $this->stateMachine->guard(FulfillmentStatus::Packing, FulfillmentStatus::Ready);

                $locked->update(['status' => FulfillmentStatus::Ready->value]);

                FulfillmentEvent::query()->create([
                    'fulfillment_id' => $locked->id,
                    'type' => 'packing_completed',
                    'description' => 'Packing completed — ready to ship.',
                    'occurred_at' => now(),
                ]);

                $this->recordOutboxEvent->handle('PackingCompleted', 'Fulfillment', $locked->id, [
                    'fulfillment_id' => $locked->id,
                    'order_id' => $locked->order_id,
                    'store_id' => $locked->store_id,
                ]);
            }

            return $locked->fresh(['items', 'events']);
        });
    }
}
