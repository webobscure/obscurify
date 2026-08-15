<?php

namespace App\Domain\Customers\Application;

use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Models\Order;
use App\Shared\Commerce\Application\RecordOutboxEvent;

final class RecordCustomerOrderView
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    public function handle(Customer $customer, Order $order): void
    {
        $this->recordOutboxEvent->handle('CustomerOrderViewed', 'Customer', $customer->id, [
            'customer_id' => $customer->id,
            'store_id' => $customer->store_id,
            'order_id' => $order->id,
        ]);
    }
}
