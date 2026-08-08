<?php

namespace App\Domain\Locations\Http\Controllers;

use App\Domain\Locations\Application\CreateLocation;
use App\Domain\Locations\Application\UpdateLocation;
use App\Domain\Locations\Http\Requests\StoreLocationRequest;
use App\Domain\Locations\Http\Requests\UpdateLocationRequest;
use App\Domain\Locations\Http\Resources\LocationResource;
use App\Domain\Locations\Models\Location;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class LocationController extends Controller
{
    /**
     * Scoped to the active tenant by Location's BelongsToTenant global
     * scope — this can never return another store's locations.
     */
    public function index(): AnonymousResourceCollection
    {
        $locations = Location::query()->orderBy('name')->get();

        return LocationResource::collection($locations);
    }

    public function store(StoreLocationRequest $request, CreateLocation $action): LocationResource
    {
        $location = $action->handle($request->validated());

        return new LocationResource($location);
    }

    public function update(UpdateLocationRequest $request, Location $location, UpdateLocation $action): LocationResource
    {
        $location = $action->handle($location, $request->validated());

        return new LocationResource($location);
    }
}
