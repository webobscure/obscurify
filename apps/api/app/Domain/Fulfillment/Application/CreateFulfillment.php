<?php

namespace App\Domain\Fulfillment\Application;

use App\Domain\Fulfillment\Enums\FulfillmentStatus;
use App\Domain\Fulfillment\Exceptions\FulfillmentOvershipmentException;
use App\Domain\Fulfillment\Models\Fulfillment;
use App\Domain\Fulfillment\Models\FulfillmentEvent;
use App\Domain\Fulfillment\Models\FulfillmentItem;
use App\Domain\Orders\Enums\FinancialStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registers a pick list against an already-paid Order (spec section 2/13).
 * Supports partial fulfillment: $lines may cover a subset of the Order's
 * items, and one OrderItem may appear across multiple Fulfillments, as
 * long as the running total of *non-cancelled* Fulfillments' quantities
 * per OrderItem never exceeds what was ordered — the same overshipment
 * discipline CreateShipment already established for Shipment/ShipmentItem,
 * applied one layer earlier.
 */
final class CreateFulfillment
{
    public function __construct(
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    /**
     * @param  list<array{order_item_id: string, quantity: int}>  $lines
     */
    public function handle(Order $order, array $lines, ?string $notes, ?string $createdBy): Fulfillment
    {
        return DB::transaction(function () use ($order, $lines, $notes, $createdBy) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->financial_status !== FinancialStatus::Paid) {
                throw ValidationException::withMessages([
                    'order' => 'This order must be paid before it can be fulfilled.',
                ]);
            }

            $orderedItems = [];

            foreach ($lines as $line) {
                // Locking every referenced OrderItem row is what makes two
                // concurrent fulfillment-create requests for the same
                // OrderItem safe — mirrors CreateShipment exactly.
                $orderItem = OrderItem::query()
                    ->where('order_id', $lockedOrder->id)
                    ->whereKey($line['order_item_id'])
                    ->lockForUpdate()
                    ->first();

                if ($orderItem === null) {
                    throw ValidationException::withMessages([
                        'lines' => "Order item \"{$line['order_item_id']}\" does not belong to this order.",
                    ]);
                }

                $alreadyFulfilled = (int) FulfillmentItem::query()
                    ->where('order_item_id', $orderItem->id)
                    ->whereHas('fulfillment', fn ($q) => $q->where('status', '!=', FulfillmentStatus::Cancelled->value))
                    ->sum('quantity');

                if ($alreadyFulfilled + $line['quantity'] > $orderItem->quantity) {
                    throw FulfillmentOvershipmentException::exceedsOrderedQuantity($orderItem->id);
                }

                $orderedItems[] = ['order_item' => $orderItem, 'quantity' => $line['quantity']];
            }

            $fulfillment = Fulfillment::query()->create([
                'order_id' => $lockedOrder->id,
                'status' => FulfillmentStatus::Pending->value,
                'notes' => $notes,
                'created_by' => $createdBy,
            ]);

            $items = new Collection;
            foreach ($orderedItems as $entry) {
                $items->push(FulfillmentItem::query()->create([
                    'fulfillment_id' => $fulfillment->id,
                    'order_item_id' => $entry['order_item']->id,
                    'quantity' => $entry['quantity'],
                ]));
            }

            FulfillmentEvent::query()->create([
                'fulfillment_id' => $fulfillment->id,
                'type' => 'created',
                'description' => 'Fulfillment created.',
                'occurred_at' => now(),
            ]);

            $this->recordOutboxEvent->handle('FulfillmentCreated', 'Fulfillment', $fulfillment->id, [
                'fulfillment_id' => $fulfillment->id,
                'order_id' => $lockedOrder->id,
                'store_id' => $fulfillment->store_id,
            ]);

            return $fulfillment->fresh(['items', 'events']);
        });
    }
}
