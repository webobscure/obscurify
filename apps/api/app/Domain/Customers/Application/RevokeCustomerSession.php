<?php

namespace App\Domain\Customers\Application;

use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerSession;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Powers "log out this device" from a session list — same revoke as
 * LogoutCustomer but callable against *any* of the customer's sessions,
 * not just the one authenticating the current request.
 */
final class RevokeCustomerSession
{
    public function __construct(private readonly LogoutCustomer $logoutCustomer) {}

    public function handle(Customer $customer, CustomerSession $session): void
    {
        if ($session->customer_id !== $customer->id) {
            throw new AuthorizationException('This session does not belong to you.');
        }

        $this->logoutCustomer->handle($session);
    }
}
