<?php

namespace App\Domain\Storefront\Http\Controllers;

use App\Domain\Orders\Models\Order;
use App\Domain\Storefront\Http\Resources\OrderConfirmationResource;
use App\Http\Controllers\Controller;

final class StorefrontOrderController extends Controller
{
    /**
     * Route-bound by the Order's own ULID, which doubles as the
     * confirmation token (see OrderConfirmationResource) — reloading the
     * confirmation URL refetches by this id, never by the guessable
     * sequential `number`. Order's BelongsToTenant global scope means a
     * Store-A hostname can never resolve a Store-B order this way either.
     */
    public function show(Order $order): OrderConfirmationResource
    {
        $order->load(['items', 'shippingAddress', 'billingAddress']);

        return new OrderConfirmationResource($order);
    }
}
