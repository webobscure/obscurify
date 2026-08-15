<?php

namespace App\Domain\Customers\Http\Controllers;

use App\Domain\Customers\Application\UpdateCustomerProfile;
use App\Domain\Customers\Http\Requests\UpdateCustomerProfileRequest;
use App\Domain\Customers\Http\Resources\CustomerResource;
use App\Domain\Customers\Support\CurrentCustomerContext;
use App\Http\Controllers\Controller;

final class CustomerAccountController extends Controller
{
    public function show(CurrentCustomerContext $currentCustomerContext): CustomerResource
    {
        $customer = $currentCustomerContext->customer();
        $customer->load('addresses');

        return new CustomerResource($customer);
    }

    public function update(
        UpdateCustomerProfileRequest $request,
        UpdateCustomerProfile $action,
        CurrentCustomerContext $currentCustomerContext,
    ): CustomerResource {
        $customer = $action->handle($currentCustomerContext->customer(), $request->validated());
        $customer->load('addresses');

        return new CustomerResource($customer);
    }
}
