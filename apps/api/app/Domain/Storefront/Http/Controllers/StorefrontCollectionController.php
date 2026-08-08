<?php

namespace App\Domain\Storefront\Http\Controllers;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Collections\Enums\CollectionStatus;
use App\Domain\Collections\Models\Collection;
use App\Domain\Storefront\Http\Resources\StorefrontCollectionResource;
use App\Domain\Storefront\Http\Resources\StorefrontProductResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class StorefrontCollectionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $collections = Collection::query()
            ->where('status', CollectionStatus::Active)
            ->orderBy('title')
            ->paginate();

        return StorefrontCollectionResource::collection($collections);
    }

    /**
     * Only an `active` collection is visible, and only its `active`
     * products — a draft collection or a Store-B product can never
     * surface here.
     */
    public function show(string $slug, Request $request): JsonResponse
    {
        $collection = Collection::query()
            ->where('slug', $slug)
            ->where('status', CollectionStatus::Active)
            ->firstOrFail();

        $products = $collection->products()
            ->where('status', ProductStatus::Active)
            ->withMin(['variants as min_variant_price' => fn ($q) => $q->where('status', ProductStatus::Active)], 'price_amount')
            ->with([
                'variants' => fn ($q) => $q->where('status', ProductStatus::Active),
                'variants.optionValues.option',
                'variants.inventoryItem.levels',
                'media',
            ])
            ->paginate();

        // `additional()` only merges a plain array — going through
        // response()->getData() instead of embedding the resource
        // collection directly preserves the paginator's data/links/meta
        // shape instead of flattening it to a bare array.
        return response()->json([
            'data' => (new StorefrontCollectionResource($collection))->resolve($request),
            'products' => StorefrontProductResource::collection($products)->response($request)->getData(true),
        ]);
    }
}
