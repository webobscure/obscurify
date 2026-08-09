<?php

namespace App\Domain\Shipping\Http\Controllers;

use App\Domain\Shipping\Application\CreateShippingZone;
use App\Domain\Shipping\Application\UpdateShippingZone;
use App\Domain\Shipping\Http\Requests\StoreShippingZoneRequest;
use App\Domain\Shipping\Http\Requests\UpdateShippingZoneRequest;
use App\Domain\Shipping\Http\Resources\ShippingZoneResource;
use App\Domain\Shipping\Models\ShippingZone;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ShippingZoneController extends Controller
{
    /**
     * Scoped to the active tenant by ShippingZone's BelongsToTenant global
     * scope — this can never return another store's zones.
     */
    public function index(): AnonymousResourceCollection
    {
        $zones = ShippingZone::query()->with('regions')->orderBy('name')->get();

        return ShippingZoneResource::collection($zones);
    }

    public function store(StoreShippingZoneRequest $request, CreateShippingZone $action): ShippingZoneResource
    {
        $zone = $action->handle($request->validated());

        return new ShippingZoneResource($zone);
    }

    /**
     * $zone is resolved via tenant-scoped route model binding: a
     * cross-tenant id yields a 404, never another store's zone.
     */
    public function update(UpdateShippingZoneRequest $request, ShippingZone $zone, UpdateShippingZone $action): ShippingZoneResource
    {
        $zone = $action->handle($zone, $request->validated());

        return new ShippingZoneResource($zone);
    }
}
