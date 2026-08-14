<?php

namespace App\Domain\Apps\Http\Controllers\Gateway;

use App\Domain\Apps\Http\Resources\Gateway\GatewayProductResource;
use App\Domain\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * `/api/apps/v1/products` — requires an AppToken (AuthenticateAppToken)
 * with `products.read`. Scoped to the token's own store by Product's
 * BelongsToTenant global scope, same as every admin controller.
 */
final class ProductGatewayController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $products = Product::query()->orderByDesc('created_at')->paginate();

        return GatewayProductResource::collection($products);
    }

    public function show(Product $product): GatewayProductResource
    {
        return new GatewayProductResource($product);
    }
}
