<?php

namespace App\Domain\Customers\Support;

use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerAccessToken;
use App\Domain\Customers\Models\CustomerSession;
use RuntimeException;

/**
 * Holds the current request's authenticated customer identity — the
 * storefront account API's analogue of Apps' CurrentAppContext, bound as
 * a singleton and populated by AuthenticateCustomerToken. Entirely
 * separate from merchant-admin auth (Sanctum's `$request->user()`) —
 * see docs/adr/022-customer-identity.md.
 */
final class CurrentCustomerContext
{
    private ?Customer $customer = null;

    private ?CustomerSession $session = null;

    private ?CustomerAccessToken $token = null;

    public function set(Customer $customer, CustomerSession $session, CustomerAccessToken $token): void
    {
        $this->customer = $customer;
        $this->session = $session;
        $this->token = $token;
    }

    public function clear(): void
    {
        $this->customer = null;
        $this->session = null;
        $this->token = null;
    }

    public function customer(): Customer
    {
        if ($this->customer === null) {
            throw new RuntimeException('No authenticated customer is active.');
        }

        return $this->customer;
    }

    public function session(): CustomerSession
    {
        if ($this->session === null) {
            throw new RuntimeException('No authenticated customer is active.');
        }

        return $this->session;
    }

    public function token(): CustomerAccessToken
    {
        if ($this->token === null) {
            throw new RuntimeException('No authenticated customer is active.');
        }

        return $this->token;
    }
}
