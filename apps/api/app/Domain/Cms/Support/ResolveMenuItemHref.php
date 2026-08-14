<?php

namespace App\Domain\Cms\Support;

use App\Domain\Catalog\Models\Product;
use App\Domain\Cms\Enums\MenuItemTargetType;
use App\Domain\Cms\Models\Blog;
use App\Domain\Cms\Models\BlogPost;
use App\Domain\Cms\Models\MenuItem;
use App\Domain\Cms\Models\Page;
use App\Domain\Collections\Models\Collection;

/**
 * Resolves a MenuItem's `target_type`/`target_id` into an actual
 * storefront href at render time — a page/product/collection/blog(-post)
 * can be renamed without the menu item itself needing an update (see
 * the migration's docblock). Returns null, never throws, when the
 * referenced row no longer exists — a since-deleted target is simply
 * skipped by the caller, not a fatal error for the whole menu.
 */
final class ResolveMenuItemHref
{
    public function handle(MenuItem $item): ?string
    {
        return match ($item->target_type) {
            MenuItemTargetType::Url => $item->url,
            MenuItemTargetType::Page => $this->slugHref(Page::class, $item->target_id, '/pages/'),
            MenuItemTargetType::Collection => $this->slugHref(Collection::class, $item->target_id, '/collections/'),
            MenuItemTargetType::Product => $this->slugHref(Product::class, $item->target_id, '/products/'),
            MenuItemTargetType::Blog => $this->slugHref(Blog::class, $item->target_id, '/blog/'),
            MenuItemTargetType::BlogPost => $this->slugHref(BlogPost::class, $item->target_id, '/blog/posts/'),
        };
    }

    /**
     * @param  class-string<Page|Collection|Product|Blog|BlogPost>  $modelClass
     */
    private function slugHref(string $modelClass, ?string $id, string $prefix): ?string
    {
        if ($id === null) {
            return null;
        }

        $model = $modelClass::query()->find($id);

        return $model === null ? null : $prefix.$model->slug;
    }
}
