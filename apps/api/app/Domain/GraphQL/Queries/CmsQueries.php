<?php

namespace App\Domain\GraphQL\Queries;

use App\Domain\Cms\Enums\SeoSubjectType;
use App\Domain\Cms\Models\ActivePageVersion;
use App\Domain\Cms\Models\Menu;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Models\SeoMetadata;
use App\Domain\Cms\Support\BuildMenuTree;
use App\Domain\GraphQL\Exceptions\GraphQLUserError;
use App\Domain\GraphQL\Support\GraphQLContext;
use App\Domain\GraphQL\Support\QueryFieldRegistry;
use App\Domain\GraphQL\Support\TypeRegistry;
use App\Domain\GraphQL\Types\CmsTypes;
use App\Domain\Themes\Support\ThemeRenderer;
use GraphQL\Type\Definition\Type;

/**
 * `pages`/`navigation` — mirrors StorefrontPageController/
 * StorefrontMenuController exactly (see those classes' own docblocks:
 * a page with no ActivePageVersion is 404, matching this resolver's
 * GraphQLUserError::notFound).
 */
final class CmsQueries
{
    public static function register(QueryFieldRegistry $queries, TypeRegistry $types): void
    {
        $queries->register('page', [
            'type' => $types->get('Page'),
            'args' => ['slug' => Type::nonNull(Type::string())],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $page = Page::query()->where('store_id', $context->store->id)->where('slug', $args['slug'])->first();

                if ($page === null) {
                    throw GraphQLUserError::notFound('Page');
                }

                $active = ActivePageVersion::query()->where('page_id', $page->id)->first();

                if ($active === null) {
                    throw GraphQLUserError::notFound('Page');
                }

                $rendered = app(ThemeRenderer::class)->renderCmsPage($context->store->id, $active->pageVersion->sections);

                $seo = SeoMetadata::query()
                    ->where('subject_type', SeoSubjectType::PageVersion->value)
                    ->where('subject_id', $active->page_version_id)
                    ->first();

                return CmsTypes::pageArray(
                    ['id' => $page->id, 'title' => $page->title, 'slug' => $page->slug],
                    $rendered,
                    $seo === null ? null : ['meta_title' => $seo->meta_title, 'meta_description' => $seo->meta_description, 'canonical_url' => $seo->canonical_url, 'og_image' => $seo->og_image],
                );
            },
        ]);

        $queries->register('navigation', [
            'type' => $types->get('Menu'),
            'args' => ['handle' => Type::nonNull(Type::string())],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $menu = Menu::query()->where('store_id', $context->store->id)->where('handle', $args['handle'])->first();

                if ($menu === null) {
                    throw GraphQLUserError::notFound('Menu');
                }

                $items = app(BuildMenuTree::class)->handle($menu);

                return ['handle' => $menu->handle, 'items' => $items->all()];
            },
        ]);
    }
}
