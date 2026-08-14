<?php

namespace App\Domain\Cms\Http\Controllers;

use App\Domain\Cms\Http\Requests\StoreMenuItemRequest;
use App\Domain\Cms\Http\Requests\UpdateMenuItemRequest;
use App\Domain\Cms\Http\Resources\MenuItemResource;
use App\Domain\Cms\Models\Menu;
use App\Domain\Cms\Models\MenuItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class MenuItemController extends Controller
{
    public function store(StoreMenuItemRequest $request, Menu $menu): MenuItemResource
    {
        $item = $menu->items()->create($request->validated());

        return new MenuItemResource($item);
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem): MenuItemResource
    {
        $menuItem->update($request->validated());

        return new MenuItemResource($menuItem);
    }

    public function destroy(MenuItem $menuItem): Response
    {
        $menuItem->delete();

        return response()->noContent();
    }
}
