<?php

namespace App\Domain\Returns\Application;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Exceptions\ReturnOverReceiptException;
use App\Domain\Returns\Models\ReturnEvent;
use App\Domain\Returns\Models\ReturnItem;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\Shipping\Enums\ShipmentStatus;
use App\Domain\Shipping\Models\ShipmentItem;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registers a return attempt against an Order (spec section 2). Only
 * *shipped* quantity is returnable — nothing else in this codebase treats
 * "ordered" as "in the customer's hands," and a return is meaningless for
 * a unit that never left the warehouse. Supports multiple ReturnRequests
 * per Order and multiple partial returns of the same OrderItem, as long
 * as the running total of *non-rejected, non-cancelled* ReturnRequests'
 * quantities per OrderItem never exceeds what was actually shipped — the
 * same overshipment discipline CreateFulfillment/CreateShipment already
 * established, applied one layer downstream.
 */
final class RequestReturn
{
    public function __construct(
        private readonly AllocateReturnNumber $allocateReturnNumber,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    /**
     * @param  list<array{order_item_id: string, quantity: int, reason: string, condition?: string|null, notes?: string|null}>  $lines
     */
    public function handle(Order $order, array $lines, ?string $notes, ?string $customerId): ReturnRequest
    {
        return DB::transaction(function () use ($order, $lines, $notes, $customerId) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $returnItems = [];

            foreach ($lines as $line) {
                // Locking every referenced OrderItem row is what makes two
                // concurrent return-request submissions for the same
                // OrderItem safe — mirrors CreateFulfillment exactly.
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

                $shippedTotal = (int) ShipmentItem::query()
                    ->where('order_item_id', $orderItem->id)
                    ->whereHas('shipment', fn ($q) => $q->where('status', '!=', ShipmentStatus::Cancelled->value))
                    ->sum('quantity');

                $alreadyReturned = (int) ReturnItem::query()
                    ->where('order_item_id', $orderItem->id)
                    ->whereHas('returnRequest', fn ($q) => $q->whereNotIn('status', [ReturnStatus::Rejected->value, ReturnStatus::Cancelled->value]))
                    ->sum('quantity');

                if ($line['quantity'] > $shippedTotal - $alreadyReturned) {
                    throw ReturnOverReceiptException::exceedsReturnableQuantity($orderItem->id);
                }

                $returnItems[] = ['order_item' => $orderItem, 'line' => $line];
            }

            $number = $this->allocateReturnNumber->handle($lockedOrder->store_id);

            $returnRequest = ReturnRequest::query()->create([
                'order_id' => $lockedOrder->id,
                'customer_id' => $customerId,
                'number' => $number,
                'status' => ReturnStatus::Requested->value,
                'requested_at' => now(),
                'notes' => $notes,
            ]);

            $items = new Collection;
            foreach ($returnItems as $entry) {
                $items->push(ReturnItem::query()->create([
                    'return_request_id' => $returnRequest->id,
                    'order_item_id' => $entry['order_item']->id,
                    'quantity' => $entry['line']['quantity'],
                    'reason' => $entry['line']['reason'],
                    'condition' => $entry['line']['condition'] ?? null,
                    'notes' => $entry['line']['notes'] ?? null,
                ]));
            }

            ReturnEvent::query()->create([
                'return_request_id' => $returnRequest->id,
                'type' => 'requested',
                'description' => 'Return requested.',
                'occurred_at' => now(),
            ]);

            $this->recordOutboxEvent->handle('ReturnRequested', 'ReturnRequest', $returnRequest->id, [
                'return_request_id' => $returnRequest->id,
                'order_id' => $lockedOrder->id,
                'store_id' => $returnRequest->store_id,
            ]);

            return $returnRequest->fresh(['items', 'events']);
        });
    }
}
