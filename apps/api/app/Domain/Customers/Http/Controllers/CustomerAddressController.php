<?php

namespace App\Domain\Customers\Http\Controllers;

use App\Domain\Customers\Application\CreateCustomerAddress;
use App\Domain\Customers\Application\DeleteCustomerAddress;
use App\Domain\Customers\Application\UpdateCustomerAddress;
use App\Domain\Customers\Http\Requests\StoreCustomerAddressRequest;
use App\Domain\Customers\Http\Requests\UpdateCustomerAddressRequest;
use App\Domain\Customers\Http\Resources\CustomerAddressResource;
use App\Domain\Customers\Models\CustomerAddress;
use App\Domain\Customers\Support\CurrentCustomerContext;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class CustomerAddressController extends Controller
{
    public function index(CurrentCustomerContext $currentCustomerContext): JsonResponse
    {
        $addresses = CustomerAddress::query()
            ->where('customer_id', $currentCustomerContext->customer()->id)
            ->orderByDesc('created_at')
            ->get();

        return CustomerAddressResource::collection($addresses)->response();
    }

    public function store(
        StoreCustomerAddressRequest $request,
        CreateCustomerAddress $action,
        CurrentCustomerContext $currentCustomerContext,
    ): JsonResponse {
        $address = $action->handle($currentCustomerContext->customer(), $request->validated());

        return (new CustomerAddressResource($address))->response()->setStatusCode(201);
    }

    public function update(
        UpdateCustomerAddressRequest $request,
        CustomerAddress $address,
        UpdateCustomerAddress $action,
        CurrentCustomerContext $currentCustomerContext,
    ): JsonResponse {
        $customer = $currentCustomerContext->customer();
        $this->assertOwnership($customer->id, $address);

        $address = $action->handle($customer, $address, $request->validated());

        return (new CustomerAddressResource($address))->response();
    }

    public function destroy(
        CustomerAddress $address,
        DeleteCustomerAddress $action,
        CurrentCustomerContext $currentCustomerContext,
    ): Response {
        $customer = $currentCustomerContext->customer();
        $this->assertOwnership($customer->id, $address);

        $action->handle($customer, $address);

        return response()->noContent();
    }

    /**
     * CustomerAddress's BelongsToTenant scope only guarantees this
     * address belongs to the active store — a second customer in the
     * same store must not be able to touch it. 403, not 404: the address
     * genuinely exists in this store, it's simply not theirs (a
     * cross-store id, by contrast, never resolves at all via route model
     * binding and 404s on its own before this method ever runs).
     */
    private function assertOwnership(string $customerId, CustomerAddress $address): void
    {
        if ($address->customer_id !== $customerId) {
            throw new AuthorizationException('This address does not belong to you.');
        }
    }
}
