<?php

namespace App\Domain\Shipping\Application;

use App\Domain\Orders\Enums\FinancialStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Shipping\Enums\ShipmentStatus;
use App\Domain\Shipping\Enums\TrackingEventStatus;
use App\Domain\Shipping\Exceptions\OvershipmentException;
use App\Domain\Shipping\Exceptions\UnknownShippingProviderException;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Models\ShipmentItem;
use App\Domain\Shipping\Models\TrackingEvent;
use App\Domain\Shipping\Support\ShipmentStateMachine;
use App\Domain\Shipping\Support\ShippingProviderRegistry;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single, transactional use case that registers a Shipment against a
 * paid Order (spec section 18). For this milestone, creation is always a
 * merchant action taken after the Order is paid — nothing dispatches this
 * automatically (spec section 15). Supports partial shipment: $lines may
 * cover a subset of the Order's items, and one OrderItem may be shipped
 * across multiple calls, as long as the running total per OrderItem never
 * exceeds what was ordered.
 */
final class CreateShipment
{
    public function __construct(
        private readonly ShippingProviderRegistry $registry,
        private readonly ShipmentStateMachine $stateMachine,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    /**
     * @param  list<array{order_item_id: string, quantity: int}>  $lines
     */
    public function handle(Order $order, string $providerCode, array $lines): Shipment
    {
        if (! $this->registry->has($providerCode)) {
            throw UnknownShippingProviderException::forCode($providerCode);
        }

        return DB::transaction(function () use ($order, $providerCode, $lines) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->financial_status !== FinancialStatus::Paid) {
                throw ValidationException::withMessages([
                    'order' => 'This order must be paid before a shipment can be created.',
                ]);
            }

            $orderedItems = [];

            foreach ($lines as $line) {
                // Locking every referenced OrderItem row is what makes
                // "total shipped never exceeds ordered" concurrency-safe
                // (spec section 38): two simultaneous shipment-create
                // requests for the same OrderItem serialize on this lock,
                // so the SUM() check below can never race.
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

                $alreadyShipped = (int) ShipmentItem::query()
                    ->where('order_item_id', $orderItem->id)
                    ->sum('quantity');

                if ($alreadyShipped + $line['quantity'] > $orderItem->quantity) {
                    throw OvershipmentException::forOrderItem($orderItem->id);
                }

                $orderedItems[] = ['order_item' => $orderItem, 'quantity' => $line['quantity']];
            }

            $shipment = Shipment::query()->create([
                'order_id' => $lockedOrder->id,
                'provider' => $providerCode,
                'status' => ShipmentStatus::Pending->value,
            ]);

            $shipmentItems = new Collection;
            foreach ($orderedItems as $entry) {
                $shipmentItems->push(ShipmentItem::query()->create([
                    'shipment_id' => $shipment->id,
                    'order_item_id' => $entry['order_item']->id,
                    'quantity' => $entry['quantity'],
                ]));
            }

            $provider = $this->registry->resolve($providerCode);
            $result = $provider->createShipment($shipment, $shipmentItems);

            $this->stateMachine->guard(ShipmentStatus::Pending, ShipmentStatus::Created);

            $shipment->update([
                'external_shipment_id' => $result->externalShipmentId,
                'tracking_number' => $result->trackingNumber,
                'tracking_url' => $result->trackingUrl,
                'status' => ShipmentStatus::Created->value,
            ]);

            TrackingEvent::query()->create([
                'shipment_id' => $shipment->id,
                'status' => TrackingEventStatus::Created->value,
                'description' => 'Shipment created.',
                'occurred_at' => now(),
            ]);

            $this->recordOutboxEvent->handle('ShipmentCreated', 'Shipment', $shipment->id, [
                'shipment_id' => $shipment->id,
                'order_id' => $lockedOrder->id,
                'store_id' => $shipment->store_id,
                'provider' => $providerCode,
            ]);

            return $shipment->fresh(['items', 'trackingEvents']);
        });
    }
}
