<?php

namespace App\Domain\Shipping\Http\Controllers;

use App\Domain\Orders\Models\Order;
use App\Domain\Shipping\Application\CancelShipment;
use App\Domain\Shipping\Application\CreateShipment;
use App\Domain\Shipping\Http\Requests\StoreOrderShipmentRequest;
use App\Domain\Shipping\Http\Resources\ShipmentResource;
use App\Domain\Shipping\Models\Shipment;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Shipment creation is a merchant action against an already-paid Order
 * (spec section 15/18) — kept off ShipmentController itself since it's
 * scoped by Order, not a bare shipments collection.
 */
final class OrderShipmentController extends Controller
{
    /**
     * $order is resolved via tenant-scoped route model binding: a
     * cross-tenant id yields a 404, never another store's order — so a
     * Shipment can never be created against another store's OrderItems.
     */
    public function store(StoreOrderShipmentRequest $request, Order $order, CreateShipment $action): JsonResponse
    {
        $data = $request->validated();

        $shipment = $action->handle($order, $data['provider'], $data['lines']);

        return (new ShipmentResource($shipment))->response()->setStatusCode(201);
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
