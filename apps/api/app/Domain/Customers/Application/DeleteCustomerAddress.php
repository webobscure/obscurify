<?php

namespace App\Domain\Customers\Application;

use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerAddress;
use App\Shared\Commerce\Application\RecordOutboxEvent;

final class DeleteCustomerAddress
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    public function handle(Customer $customer, CustomerAddress $address): void
    {
        $address->delete();

        $this->recordOutboxEvent->handle('CustomerAddressUpdated', 'Customer', $customer->id, [
            'customer_id' => $customer->id,
            'store_id' => $customer->store_id,
            'customer_address_id' => $address->id,
            'action' => 'deleted',
        ]);
    }
}
