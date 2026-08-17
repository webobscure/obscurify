<?php

namespace App\Domain\Inventory\Http\Controllers;

use App\Domain\Inventory\Application\AdjustInventory;
use App\Domain\Inventory\Http\Requests\AdjustInventoryRequest;
use App\Domain\Inventory\Http\Requests\IndexInventoryRequest;
use App\Domain\Inventory\Http\Resources\InventoryItemResource;
use App\Domain\Inventory\Http\Resources\InventoryLevelResource;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Locations\Models\Location;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class InventoryController extends Controller
{
    /**
     * Scoped to the active tenant by InventoryItem's BelongsToTenant global
     * scope — this can never return another store's inventory.
     *
     * `?product_variant_id[]=` (docs/design/DESIGN_SYSTEM.md Products
     * redesign) scopes the result to exactly the requested variants and
     * skips pagination — the caller already knows which (small, bounded)
     * set of variants it needs, unlike the unfiltered store-wide list
     * below which stays paginated as before.
     */
    public function index(IndexInventoryRequest $request): AnonymousResourceCollection
    {
        $query = InventoryItem::query()->with(['levels', 'variant']);

        if ($variantIds = $request->validated('product_variant_id')) {
            $items = $query->whereIn('product_variant_id', $variantIds)->get();

            return InventoryItemResource::collection($items);
        }

        $items = $query->orderByDesc('created_at')->paginate();

        return InventoryItemResource::collection($items);
    }

    /**
     * $item is resolved via tenant-scoped route model binding: a
     * cross-tenant id yields a 404, never another store's inventory item.
     */
    public function adjust(AdjustInventoryRequest $request, InventoryItem $item, AdjustInventory $action): JsonResponse
    {
        $data = $request->validated();

        // Tenant-scoped: a Store B location id simply fails to resolve
        // while this store is active, before AdjustInventory's own
        // defense-in-depth store match check ever runs.
        $location = Location::query()->findOrFail($data['location_id']);

        $level = $action->handle($item, $location, $data, $request->user()?->id);

        // Always 200: this is an action endpoint, not a resource-creation
        // endpoint — whether the underlying InventoryLevel row already
        // existed is an implementation detail callers shouldn't see
        // reflected in the status code (Laravel would otherwise auto-201
        // the first time a level is provisioned for a location).
        return (new InventoryLevelResource($level))->response()->setStatusCode(200);
    }
}
