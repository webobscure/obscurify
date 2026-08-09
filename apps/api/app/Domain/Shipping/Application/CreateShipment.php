<?php

namespace App\Domain\Shipping\Application;

use App\Domain\Fulfillment\Application\CompleteFulfillment;
use App\Domain\Fulfillment\Enums\FulfillmentStatus;
use App\Domain\Fulfillment\Models\Fulfillment;
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
 * The single, transactional use case that registers a Shipment (spec
 * section 18/spec Milestone 7 section 12). A Shipment must reference a
 * Fulfillment — Fulfillment Core (Milestone 7) removed the old
 * "create a shipment directly from an Order" path entirely, since
 * "prepare products for shipment" (Fulfillment) and "deliver the package"
 * (Shipping) are deliberately separate concerns now: this is always a
 * merchant action taken against a `ready` Fulfillment (which itself can
 * only exist against a paid Order — see CreateFulfillment), never
 * automatic. $lines are derived from the Fulfillment's own
 * FulfillmentItems (their `quantity`, i.e. what packing already
 * confirmed), not passed in by the caller.
 *
 * On success, delegates to Fulfillment\Application\CompleteFulfillment in
 * the same transaction — that's where reservation consumption and the
 * FulfillmentCompleted inventory movements actually happen (spec section
 * 7's invariant), keeping "what does completing a fulfillment mean"
 * entirely owned by the Fulfillment domain even though Shipping is what
 * triggers it.
 */
final class CreateShipment
{
    public function __construct(
        private readonly ShippingProviderRegistry $registry,
        private readonly ShipmentStateMachine $stateMachine,
        private readonly RecordOutboxEvent $recordOutboxEvent,
        private readonly CompleteFulfillment $completeFulfillment,
    ) {}

    /**
     * $simulate is a dev/test-only failure trigger (spec section 15),
     * threaded through to Shipment.metadata so FakeShippingProvider can
     * read it — see that class's maybeSimulateCreationFailure() docblock
     * for why it's carried on the model rather than an extra contract
     * parameter. A real provider never reads this key.
     */
    public function handle(Fulfillment $fulfillment, string $providerCode, ?string $simulate = null): Shipment
    {
        if (! $this->registry->has($providerCode)) {
            throw UnknownShippingProviderException::forCode($providerCode);
        }

        return DB::transaction(function () use ($fulfillment, $providerCode, $simulate) {
            $lockedFulfillment = Fulfillment::query()->whereKey($fulfillment->id)->lockForUpdate()->firstOrFail();

            if ($lockedFulfillment->status !== FulfillmentStatus::Ready) {
                throw ValidationException::withMessages([
                    'fulfillment' => 'This fulfillment must be fully packed (ready) before a shipment can be created.',
                ]);
            }

            $lockedOrder = Order::query()->whereKey($lockedFulfillment->order_id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->financial_status !== FinancialStatus::Paid) {
                throw ValidationException::withMessages([
                    'order' => 'This order must be paid before a shipment can be created.',
                ]);
            }

            $fulfillmentItems = $lockedFulfillment->items()->lockForUpdate()->get();
            $orderedItems = [];

            foreach ($fulfillmentItems as $fulfillmentItem) {
                // Locking every referenced OrderItem row is what makes
                // "total shipped never exceeds ordered" concurrency-safe
                // across simultaneous shipment-create requests, exactly as
                // before Milestone 7 — this check is a second, independent
                // safety net on top of Fulfillment's own overshipment
                // protection at allocation time (spec section 13).
                $orderItem = OrderItem::query()
                    ->where('order_id', $lockedOrder->id)
                    ->whereKey($fulfillmentItem->order_item_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $alreadyShipped = (int) ShipmentItem::query()
                    ->where('order_item_id', $orderItem->id)
                    ->sum('quantity');

                if ($alreadyShipped + $fulfillmentItem->quantity > $orderItem->quantity) {
                    throw OvershipmentException::forOrderItem($orderItem->id);
                }

                $orderedItems[] = ['order_item' => $orderItem, 'quantity' => $fulfillmentItem->quantity];
            }

            $shipment = Shipment::query()->create([
                'order_id' => $lockedOrder->id,
                'fulfillment_id' => $lockedFulfillment->id,
                'provider' => $providerCode,
                'status' => ShipmentStatus::Pending->value,
                'metadata' => $simulate !== null ? ['simulate' => $simulate] : null,
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
                'fulfillment_id' => $lockedFulfillment->id,
                'store_id' => $shipment->store_id,
                'provider' => $providerCode,
            ]);

            $this->completeFulfillment->handle($lockedFulfillment);

            return $shipment->fresh(['items', 'trackingEvents']);
        });
    }
}
