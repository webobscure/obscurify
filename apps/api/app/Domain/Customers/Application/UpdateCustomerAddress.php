<?php

namespace App\Domain\Customers\Application;

use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerAddress;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;

final class UpdateCustomerAddress
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Customer $customer, CustomerAddress $address, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $address, $data) {
            if (($data['is_default_billing'] ?? false) === true) {
                $this->clearDefault($customer, $address, 'is_default_billing');
            }

            if (($data['is_default_shipping'] ?? false) === true) {
                $this->clearDefault($customer, $address, 'is_default_shipping');
            }

            $address->fill($data)->save();

            $this->recordOutboxEvent->handle('CustomerAddressUpdated', 'Customer', $customer->id, [
                'customer_id' => $customer->id,
                'store_id' => $customer->store_id,
                'customer_address_id' => $address->id,
                'action' => 'updated',
            ]);

            return $address;
        });
    }

    private function clearDefault(Customer $customer, CustomerAddress $except, string $column): void
    {
        CustomerAddress::query()
            ->where('customer_id', $customer->id)
            ->where('id', '!=', $except->id)
            ->where($column, true)
            ->update([$column => false]);
    }
}
