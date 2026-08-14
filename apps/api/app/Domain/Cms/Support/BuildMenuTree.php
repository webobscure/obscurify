<?php

namespace App\Domain\Cms\Support;

use App\Domain\Cms\Models\Menu;
use App\Domain\Cms\Models\MenuItem;
use Illuminate\Support\Collection;

/**
 * Builds a Menu's full nested item tree in memory from one flat query —
 * a menu's items are a handful of rows at most, so this is the standard
 * adjacency-list-to-tree pattern rather than N+1 querying per level.
 * Shared by the admin (MenuController) and storefront (StorefrontMenuController)
 * so there is exactly one tree-building implementation.
 */
final class BuildMenuTree
{
    /**
     * @return Collection<int, MenuItem>
     */
    public function handle(Menu $menu): Collection
    {
        $allItems = MenuItem::query()->where('menu_id', $menu->id)->orderBy('position')->get();
        $byParent = $allItems->groupBy('parent_id');

        $assign = function (Collection $items) use (&$assign, $byParent): Collection {
            return $items->each(function (MenuItem $item) use (&$assign, $byParent) {
                $item->setRelation('children', $assign($byParent->get($item->id, collect())));
            });
        };

        return $assign($byParent->get(null, collect()));
    }
}
