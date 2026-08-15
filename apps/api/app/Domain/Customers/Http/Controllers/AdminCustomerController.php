<?php

namespace App\Domain\Customers\Http\Controllers;

use App\Domain\Customers\Http\Resources\CustomerActivityEventResource;
use App\Domain\Customers\Http\Resources\CustomerAddressResource;
use App\Domain\Customers\Http\Resources\CustomerResource;
use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Http\Resources\OrderResource;
use App\Domain\Orders\Models\Order;
use App\Domain\Returns\Http\Resources\ReturnResource;
use App\Http\Controllers\Controller;
use App\Shared\Commerce\Models\OutboxEvent;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Merchant-admin customer management (spec section 12) — nested under
 * `auth:sanctum` + `tenant` like every other admin resource, entirely
 * separate from the customer-portal controllers in this same domain
 * (which authenticate via `customer-token` instead). Read-only: there is
 * no admin "edit customer" action in this milestone, since profile edits
 * belong to the customer themselves (see CustomerAccountController).
 */
final class AdminCustomerController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $customers = Customer::query()
            ->orderByDesc('created_at')
            ->paginate();

        return CustomerResource::collection($customers);
    }

    public function show(Customer $customer): CustomerResource
    {
        $customer->load('addresses');

        return new CustomerResource($customer);
    }

    public function orders(Customer $customer): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->where('customer_id', $customer->id)
            ->with(['customer'])
            ->orderByDesc('number')
            ->paginate();

        return OrderResource::collection($orders);
    }

    public function returns(Customer $customer): AnonymousResourceCollection
    {
        $returns = $customer->returns()
            ->with(['items', 'events'])
            ->orderByDesc('created_at')
            ->paginate();

        return ReturnResource::collection($returns);
    }

    public function addresses(Customer $customer): AnonymousResourceCollection
    {
        return CustomerAddressResource::collection($customer->addresses()->orderByDesc('created_at')->get());
    }

    /**
     * Spec section 12's "Activity timeline" reads directly off the
     * existing OutboxEvent table filtered to this Customer's aggregate —
     * the same "reuse the platform event log as the audit trail" pattern
     * Apps used for its own audit surface (docs/adr/018-app-platform.md),
     * rather than a bespoke per-domain event table. See
     * docs/adr/022-customer-identity.md.
     */
    public function activity(Customer $customer): AnonymousResourceCollection
    {
        $events = OutboxEvent::query()
            ->where('aggregate_type', 'Customer')
            ->where('aggregate_id', $customer->id)
            ->orderByDesc('occurred_at')
            ->paginate();

        return CustomerActivityEventResource::collection($events);
    }
}
