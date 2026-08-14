<?php

namespace App\Domain\Apps\Http\Controllers\Gateway;

use App\Domain\Apps\Http\Resources\Gateway\GatewayShippingMethodResource;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ShippingGatewayController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $methods = ShippingMethod::query()->orderBy('name')->get();

        return GatewayShippingMethodResource::collection($methods);
    }
}
