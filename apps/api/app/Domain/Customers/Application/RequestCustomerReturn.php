<?php

namespace App\Domain\Customers\Application;

use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Models\Order;
use App\Domain\Returns\Application\RequestReturn;
use App\Domain\Returns\Models\ReturnRequest;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Wraps the existing (merchant-facing) RequestReturn with the ownership
 * check it doesn't itself perform — RequestReturn::handle() accepts any
 * customer_id in its payload without verifying it against the order,
 * which is fine for a trusted staff user but not for a customer-portal
 * caller. This is the only gate keeping a customer from filing a return
 * against someone else's order.
 */
final class RequestCustomerReturn
{
    public function __construct(
        private readonly RequestReturn $requestReturn,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    /**
     * @param  list<array{order_item_id: string, quantity: int, reason: string, condition?: string, notes?: string}>  $lines
     */
    public function handle(Customer $customer, Order $order, array $lines, ?string $notes): ReturnRequest
    {
        if ($order->customer_id !== $customer->id) {
            throw new AuthorizationException('This order does not belong to you.');
        }

        $returnRequest = $this->requestReturn->handle($order, $lines, $notes, $customer->id);

        $this->recordOutboxEvent->handle('CustomerReturnRequested', 'Customer', $customer->id, [
            'customer_id' => $customer->id,
            'store_id' => $customer->store_id,
            'order_id' => $order->id,
            'return_request_id' => $returnRequest->id,
        ]);

        return $returnRequest;
    }
}
