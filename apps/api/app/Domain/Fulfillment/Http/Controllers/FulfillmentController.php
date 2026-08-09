<?php

namespace App\Domain\Fulfillment\Http\Controllers;

use App\Domain\Fulfillment\Application\AllocateFulfillment;
use App\Domain\Fulfillment\Application\CancelFulfillment;
use App\Domain\Fulfillment\Application\CreateFulfillment;
use App\Domain\Fulfillment\Application\PackFulfillmentItems;
use App\Domain\Fulfillment\Application\PickFulfillmentItems;
use App\Domain\Fulfillment\Http\Requests\CompleteFulfillmentRequest;
use App\Domain\Fulfillment\Http\Requests\PackFulfillmentRequest;
use App\Domain\Fulfillment\Http\Requests\PickFulfillmentRequest;
use App\Domain\Fulfillment\Http\Requests\StoreFulfillmentRequest;
use App\Domain\Fulfillment\Http\Requests\UpdateFulfillmentRequest;
use App\Domain\Fulfillment\Http\Resources\FulfillmentResource;
use App\Domain\Fulfillment\Models\Fulfillment;
use App\Domain\Orders\Models\Order;
use App\Domain\Shipping\Application\CreateShipment;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class FulfillmentController extends Controller
{
    private const array WITH = ['items.allocations', 'events', 'shipments.items', 'shipments.trackingEvents'];

    /**
     * Scoped to the active tenant by Fulfillment's BelongsToTenant global
     * scope — this can never return another store's fulfillments.
     */
    public function index(): AnonymousResourceCollection
    {
        $fulfillments = Fulfillment::query()
            ->with(self::WITH)
            ->orderByDesc('created_at')
            ->paginate();

        return FulfillmentResource::collection($fulfillments);
    }

    /**
     * $fulfillment is resolved via tenant-scoped route model binding: a
     * cross-tenant id yields a 404, never another store's fulfillment.
     */
    public function show(Fulfillment $fulfillment): FulfillmentResource
    {
        $fulfillment->load(self::WITH);

        return new FulfillmentResource($fulfillment);
    }

    /**
     * $order is resolved via tenant-scoped route model binding.
     */
    public function store(StoreFulfillmentRequest $request, Order $order, CreateFulfillment $action): JsonResponse
    {
        $data = $request->validated();

        $fulfillment = $action->handle($order, $data['items'], $data['notes'] ?? null, $request->user()?->id);

        return (new FulfillmentResource($fulfillment->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function update(UpdateFulfillmentRequest $request, Fulfillment $fulfillment): FulfillmentResource
    {
        $fulfillment->update($request->validated());

        return new FulfillmentResource($fulfillment->load(self::WITH));
    }

    public function allocate(Fulfillment $fulfillment, AllocateFulfillment $action): FulfillmentResource
    {
        $fulfillment = $action->handle($fulfillment);

        return new FulfillmentResource($fulfillment->load(self::WITH));
    }

    public function pick(PickFulfillmentRequest $request, Fulfillment $fulfillment, PickFulfillmentItems $action): FulfillmentResource
    {
        $fulfillment = $action->handle($fulfillment, $request->validated()['items']);

        return new FulfillmentResource($fulfillment->load(self::WITH));
    }

    public function pack(PackFulfillmentRequest $request, Fulfillment $fulfillment, PackFulfillmentItems $action): FulfillmentResource
    {
        $fulfillment = $action->handle($fulfillment, $request->validated()['items']);

        return new FulfillmentResource($fulfillment->load(self::WITH));
    }

    /**
     * Creates the Shipment against this (ready) Fulfillment — see
     * Shipping\Application\CreateShipment, which also completes the
     * Fulfillment (consumes reservations) in the same transaction on
     * success.
     */
    public function complete(CompleteFulfillmentRequest $request, Fulfillment $fulfillment, CreateShipment $action): FulfillmentResource
    {
        $action->handle($fulfillment, $request->validated()['provider']);

        return new FulfillmentResource($fulfillment->fresh(self::WITH));
    }

    public function cancel(Fulfillment $fulfillment, CancelFulfillment $action): FulfillmentResource
    {
        $fulfillment = $action->handle($fulfillment);

        return new FulfillmentResource($fulfillment->load(self::WITH));
    }
}
