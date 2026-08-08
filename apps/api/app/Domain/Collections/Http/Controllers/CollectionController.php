<?php

namespace App\Domain\Collections\Http\Controllers;

use App\Domain\Collections\Application\CreateCollection;
use App\Domain\Collections\Application\DeleteCollection;
use App\Domain\Collections\Application\UpdateCollection;
use App\Domain\Collections\Http\Requests\StoreCollectionRequest;
use App\Domain\Collections\Http\Requests\UpdateCollectionRequest;
use App\Domain\Collections\Http\Resources\CollectionResource;
use App\Domain\Collections\Models\Collection;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class CollectionController extends Controller
{
    /**
     * Scoped to the active tenant by Collection's BelongsToTenant global
     * scope — this can never return another store's collections.
     */
    public function index(): AnonymousResourceCollection
    {
        $collections = Collection::query()->orderByDesc('created_at')->paginate();

        return CollectionResource::collection($collections);
    }

    public function store(StoreCollectionRequest $request, CreateCollection $action): CollectionResource
    {
        $collection = $action->handle($request->validated());

        return new CollectionResource($collection);
    }

    /**
     * $collection is resolved via tenant-scoped route model binding: a
     * cross-tenant id yields a 404, never another store's collection.
     */
    public function show(Collection $collection): CollectionResource
    {
        return new CollectionResource($collection->load('products'));
    }

    public function update(UpdateCollectionRequest $request, Collection $collection, UpdateCollection $action): CollectionResource
    {
        $collection = $action->handle($collection, $request->validated());

        return new CollectionResource($collection);
    }

    public function destroy(Collection $collection, DeleteCollection $action): Response
    {
        $action->handle($collection);

        return response()->noContent();
    }
}
