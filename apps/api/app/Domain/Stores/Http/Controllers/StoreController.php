<?php

namespace App\Domain\Stores\Http\Controllers;

use App\Domain\Stores\Application\ActivateStore;
use App\Domain\Stores\Application\CreateStore;
use App\Domain\Stores\Http\Requests\CreateStoreRequest;
use App\Domain\Stores\Http\Resources\StoreResource;
use App\Domain\Stores\Models\Store;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class StoreController extends Controller
{
    /**
     * Stores the authenticated user belongs to (not a global listing).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $stores = $request->user()->stores()->orderBy('name')->get();

        return StoreResource::collection($stores);
    }

    public function store(CreateStoreRequest $request, CreateStore $action): StoreResource
    {
        $store = $action->handle($request->user(), $request->validated());

        return new StoreResource($store);
    }

    public function show(Request $request, Store $store): StoreResource
    {
        $this->authorize('view', $store);

        return new StoreResource($store);
    }

    public function activate(Request $request, Store $store, ActivateStore $action): StoreResource
    {
        $store = $action->handle($request->user(), $store);

        return new StoreResource($store);
    }
}
