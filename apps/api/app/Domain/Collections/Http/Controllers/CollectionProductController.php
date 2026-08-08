<?php

namespace App\Domain\Collections\Http\Controllers;

use App\Domain\Catalog\Models\Product;
use App\Domain\Collections\Application\AttachProductToCollection;
use App\Domain\Collections\Application\DetachProductFromCollection;
use App\Domain\Collections\Http\Resources\CollectionResource;
use App\Domain\Collections\Models\Collection;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class CollectionProductController extends Controller
{
    public function store(Collection $collection, Product $product, AttachProductToCollection $action): CollectionResource
    {
        $action->handle($collection, $product);

        return new CollectionResource($collection->load('products'));
    }

    public function destroy(Collection $collection, Product $product, DetachProductFromCollection $action): Response
    {
        $action->handle($collection, $product);

        return response()->noContent();
    }
}
