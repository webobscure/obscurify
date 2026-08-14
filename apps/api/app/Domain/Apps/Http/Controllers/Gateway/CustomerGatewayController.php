<?php

namespace App\Domain\Apps\Http\Controllers\Gateway;

use App\Domain\Apps\Http\Resources\Gateway\GatewayCustomerResource;
use App\Domain\Customers\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CustomerGatewayController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $customers = Customer::query()->orderByDesc('created_at')->paginate();

        return GatewayCustomerResource::collection($customers);
    }

    public function show(Customer $customer): GatewayCustomerResource
    {
        return new GatewayCustomerResource($customer);
    }
}
