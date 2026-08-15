<?php

namespace App\Domain\Customers\Application;

use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerAddress;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;

final class CreateCustomerAddress
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Customer $customer, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $data) {
            if ($data['is_default_billing'] ?? false) {
                $this->clearDefault($customer, 'is_default_billing');
            }

            if ($data['is_default_shipping'] ?? false) {
                $this->clearDefault($customer, 'is_default_shipping');
            }

            $address = CustomerAddress::query()->create([
                'customer_id' => $customer->id,
                ...$data,
            ]);

            $this->recordOutboxEvent->handle('CustomerAddressUpdated', 'Customer', $customer->id, [
                'customer_id' => $customer->id,
                'store_id' => $customer->store_id,
                'customer_address_id' => $address->id,
                'action' => 'created',
            ]);

            return $address;
        });
    }

    private function clearDefault(Customer $customer, string $column): void
    {
        CustomerAddress::query()
            ->where('customer_id', $customer->id)
            ->where($column, true)
            ->update([$column => false]);
    }
}
