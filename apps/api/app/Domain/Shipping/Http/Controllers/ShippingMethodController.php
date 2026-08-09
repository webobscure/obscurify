<?php

namespace App\Domain\Shipping\Http\Controllers;

use App\Domain\Shipping\Application\CreateShippingMethod;
use App\Domain\Shipping\Application\UpdateShippingMethod;
use App\Domain\Shipping\Http\Requests\StoreShippingMethodRequest;
use App\Domain\Shipping\Http\Requests\UpdateShippingMethodRequest;
use App\Domain\Shipping\Http\Resources\ShippingMethodResource;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ShippingMethodController extends Controller
{
    /**
     * Scoped to the active tenant by ShippingMethod's BelongsToTenant
     * global scope — this can never return another store's methods.
     */
    public function index(): AnonymousResourceCollection
    {
        $methods = ShippingMethod::query()->with('zones')->orderBy('name')->get();

        return ShippingMethodResource::collection($methods);
    }

    public function store(StoreShippingMethodRequest $request, CreateShippingMethod $action): ShippingMethodResource
    {
        $method = $action->handle($request->validated());

        return new ShippingMethodResource($method);
    }

    /**
     * $method is resolved via tenant-scoped route model binding: a
     * cross-tenant id yields a 404, never another store's method.
     */
    public function update(UpdateShippingMethodRequest $request, ShippingMethod $method, UpdateShippingMethod $action): ShippingMethodResource
    {
        $method = $action->handle($method, $request->validated());

        return new ShippingMethodResource($method);
    }
}
