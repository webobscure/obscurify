<?php

namespace App\Domain\Apps\Http\Controllers\Gateway;

use App\Domain\Apps\Http\Resources\Gateway\GatewayOrderResource;
use App\Domain\Orders\Models\Order;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class OrderGatewayController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $orders = Order::query()->orderByDesc('created_at')->paginate();

        return GatewayOrderResource::collection($orders);
    }

    public function show(Order $order): GatewayOrderResource
    {
        return new GatewayOrderResource($order);
    }
}
