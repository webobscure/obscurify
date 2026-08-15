<?php

namespace App\Domain\Automation\Support;

use App\Domain\Customers\Models\Customer;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Stores\Models\Store;
use App\Shared\Commerce\Models\OutboxEvent;

/**
 * Builds a WorkflowExecution's variable Context from the OutboxEvent
 * that triggered it (spec section 7: Customer/Order/Payment/Shipment/
 * Return/Inventory/Store/Trigger payload). Resolution follows the
 * event's `aggregate_type` outward to the entities a merchant would
 * expect available for that trigger — e.g. an OrderPaymentConfirmed
 * trigger (aggregate_type=Order) also exposes `customer`, since the
 * order's own customer is always a natural variable for that trigger,
 * not just the order itself.
 *
 * Every resolved model is serialized with `toArray()` — safe here
 * because the context only ever leaves this store's own tenant
 * boundary (it is read by WorkflowConditionEvaluator/ActionExecutor
 * within the same execution, and surfaced back to that store's own
 * admin via WorkflowExecutionStep.input for the execution log).
 */
final class WorkflowVariableResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(OutboxEvent $event, Store $store): array
    {
        $context = [
            'trigger' => [
                'event_type' => $event->event_type,
                'payload' => $event->payload,
            ],
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
            ],
        ];

        [$order, $customer, $payment, $shipment, $return, $inventoryItem] = match ($event->aggregate_type) {
            'Order' => $this->fromOrder($event->aggregate_id),
            'Customer' => $this->fromCustomer($event->aggregate_id),
            'Payment' => $this->fromPayment($event->aggregate_id),
            'ReturnRequest' => $this->fromReturn($event->aggregate_id),
            'Shipment' => $this->fromShipment($event->aggregate_id),
            'InventoryItem' => [null, null, null, null, null, InventoryItem::query()->find($event->aggregate_id)],
            default => [null, null, null, null, null, null],
        };

        if ($order !== null) {
            $context['order'] = $order->toArray();
        }

        if ($customer !== null) {
            $context['customer'] = $customer->toArray();
        }

        if ($payment !== null) {
            $context['payment'] = $payment->toArray();
        }

        if ($shipment !== null) {
            $context['shipment'] = $shipment->toArray();
        }

        if ($return !== null) {
            $context['return'] = $return->toArray();
        }

        if ($inventoryItem !== null) {
            $context['inventory'] = $inventoryItem->toArray();
        }

        return $context;
    }

    /**
     * @return array{0: ?Order, 1: ?Customer, 2: null, 3: null, 4: null, 5: null}
     */
    private function fromOrder(string $orderId): array
    {
        $order = Order::query()->find($orderId);

        return [$order, $order?->customer, null, null, null, null];
    }

    /**
     * @return array{0: null, 1: ?Customer, 2: null, 3: null, 4: null, 5: null}
     */
    private function fromCustomer(string $customerId): array
    {
        return [null, Customer::query()->find($customerId), null, null, null, null];
    }

    /**
     * @return array{0: ?Order, 1: ?Customer, 2: ?Payment, 3: null, 4: null, 5: null}
     */
    private function fromPayment(string $paymentId): array
    {
        $payment = Payment::query()->find($paymentId);
        $order = $payment !== null ? Order::query()->find($payment->order_id) : null;

        return [$order, $order?->customer, $payment, null, null, null];
    }

    /**
     * @return array{0: ?Order, 1: ?Customer, 2: null, 3: null, 4: ?ReturnRequest, 5: null}
     */
    private function fromReturn(string $returnRequestId): array
    {
        $return = ReturnRequest::query()->find($returnRequestId);
        $order = $return !== null ? Order::query()->find($return->order_id) : null;

        $customer = null;

        if ($order !== null) {
            $customer = $order->customer;
        } elseif ($return !== null && $return->customer_id !== null) {
            $customer = Customer::query()->find($return->customer_id);
        }

        return [$order, $customer, null, null, $return, null];
    }

    /**
     * @return array{0: ?Order, 1: ?Customer, 2: null, 3: ?Shipment, 4: null, 5: null}
     */
    private function fromShipment(string $shipmentId): array
    {
        $shipment = Shipment::query()->find($shipmentId);
        $order = $shipment !== null ? Order::query()->find($shipment->order_id) : null;

        return [$order, $order?->customer, null, $shipment, null, null];
    }
}
