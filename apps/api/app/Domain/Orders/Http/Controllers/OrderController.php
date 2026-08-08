<?php

namespace App\Domain\Orders\Http\Controllers;

use App\Domain\Orders\Http\Resources\OrderResource;
use App\Domain\Orders\Models\Order;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only this milestone: no payment/refund/fulfillment actions exist
 * yet (no PaymentGateway, no shipping provider) — see spec section 34.
 */
final class OrderController extends Controller
{
    /**
     * Scoped to the active tenant by Order's BelongsToTenant global scope
     * — this can never return another store's orders.
     */
    public function index(): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->with(['customer'])
            ->orderByDesc('number')
            ->paginate();

        return OrderResource::collection($orders);
    }

    /**
     * $order is resolved via tenant-scoped route model binding: a
     * cross-tenant id yields a 404, never another store's order.
     */
    public function show(Order $order): OrderResource
    {
        $order->load(['customer', 'items', 'shippingAddress', 'billingAddress']);

        return new OrderResource($order);
    }
}
