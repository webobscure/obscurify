<?php

namespace App\Domain\Storefront\Http\Controllers;

use App\Domain\Cms\Models\Menu;
use App\Domain\Cms\Support\BuildMenuTree;
use App\Domain\Storefront\Http\Resources\StorefrontMenuItemResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class StorefrontMenuController extends Controller
{
    public function show(string $handle, BuildMenuTree $buildMenuTree): AnonymousResourceCollection
    {
        $menu = Menu::query()->where('handle', $handle)->firstOrFail();

        return StorefrontMenuItemResource::collection($buildMenuTree->handle($menu));
    }
}
