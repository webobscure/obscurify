<?php

namespace App\Domain\Cms\Http\Controllers;

use App\Domain\Cms\Http\Requests\StoreMenuRequest;
use App\Domain\Cms\Http\Requests\UpdateMenuRequest;
use App\Domain\Cms\Http\Resources\MenuResource;
use App\Domain\Cms\Models\Menu;
use App\Domain\Cms\Support\BuildMenuTree;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class MenuController extends Controller
{
    public function __construct(private readonly BuildMenuTree $buildMenuTree) {}

    public function index(): AnonymousResourceCollection
    {
        $menus = Menu::query()->orderByDesc('created_at')->get();

        return MenuResource::collection($menus);
    }

    public function show(Menu $menu): MenuResource
    {
        $menu->setRelation('topLevelItems', $this->buildMenuTree->handle($menu));

        return new MenuResource($menu);
    }

    public function store(StoreMenuRequest $request): MenuResource
    {
        $menu = Menu::query()->create($request->validated());
        $menu->setRelation('topLevelItems', collect());

        return new MenuResource($menu);
    }

    public function update(UpdateMenuRequest $request, Menu $menu): MenuResource
    {
        $menu->update($request->validated());
        $menu->setRelation('topLevelItems', $this->buildMenuTree->handle($menu));

        return new MenuResource($menu);
    }

    public function destroy(Menu $menu): Response
    {
        $menu->delete();

        return response()->noContent();
    }
}
