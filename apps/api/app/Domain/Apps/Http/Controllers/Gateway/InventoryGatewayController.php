<?php

namespace App\Domain\Apps\Http\Controllers\Gateway;

use App\Domain\Inventory\Application\AdjustInventory;
use App\Domain\Inventory\Http\Requests\AdjustInventoryRequest;
use App\Domain\Inventory\Http\Resources\InventoryItemResource;
use App\Domain\Inventory\Http\Resources\InventoryLevelResource;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Locations\Models\Location;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Reuses the admin domain's own AdjustInventory action/resources
 * directly — inventory adjustment has exactly one correct
 * implementation regardless of caller, and duplicating it here would
 * risk the two paths drifting apart.
 */
final class InventoryGatewayController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $items = InventoryItem::query()->with(['levels', 'variant'])->orderByDesc('created_at')->paginate();

        return InventoryItemResource::collection($items);
    }

    public function adjust(AdjustInventoryRequest $request, InventoryItem $item, AdjustInventory $action): JsonResponse
    {
        $data = $request->validated();
        $location = Location::query()->findOrFail($data['location_id']);

        $level = $action->handle($item, $location, $data, null);

        return (new InventoryLevelResource($level))->response()->setStatusCode(200);
    }
}
