<?php

namespace App\Domain\Customers\Application;

use App\Domain\Customers\Models\Customer;
use App\Shared\Commerce\Application\RecordOutboxEvent;

/**
 * Deliberately does not accept `email` — the login identifier lives on
 * CustomerIdentity, and changing it has bigger implications (identity
 * uniqueness, re-verification) than a simple profile edit; out of scope
 * for this milestone (see docs/adr/022-customer-identity.md).
 */
final class UpdateCustomerProfile
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    /**
     * @param  array{first_name?: string|null, last_name?: string|null, phone?: string|null}  $data
     */
    public function handle(Customer $customer, array $data): Customer
    {
        $customer->fill($data)->save();

        $this->recordOutboxEvent->handle('CustomerUpdated', 'Customer', $customer->id, [
            'customer_id' => $customer->id,
            'store_id' => $customer->store_id,
        ]);

        return $customer;
    }
}
