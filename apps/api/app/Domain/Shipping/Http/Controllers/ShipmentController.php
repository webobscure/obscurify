<?php

namespace App\Domain\Shipping\Http\Controllers;

use App\Domain\Shipping\Application\CancelShipment;
use App\Domain\Shipping\Http\Resources\ShipmentResource;
use App\Domain\Shipping\Models\Shipment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ShipmentController extends Controller
{
    /**
     * Scoped to the active tenant by Shipment's BelongsToTenant global
     * scope — this can never return another store's shipments.
     */
    public function index(): AnonymousResourceCollection
    {
        $shipments = Shipment::query()
            ->with(['items', 'trackingEvents'])
            ->orderByDesc('created_at')
            ->paginate();

        return ShipmentResource::collection($shipments);
    }

    /**
     * $shipment is resolved via tenant-scoped route model binding: a
     * cross-tenant id yields a 404, never another store's shipment.
     */
    public function show(Shipment $shipment): ShipmentResource
    {
        $shipment->load(['items', 'trackingEvents']);

        return new ShipmentResource($shipment);
    }

    /**
     * $shipment is resolved via tenant-scoped route model binding.
     */
    public function cancel(Shipment $shipment, CancelShipment $action): ShipmentResource
    {
        $shipment = $action->handle($shipment);

        return new ShipmentResource($shipment);
    }
}
