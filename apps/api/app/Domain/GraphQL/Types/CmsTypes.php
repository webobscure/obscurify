<?php

namespace App\Domain\GraphQL\Types;

use App\Domain\Cms\Models\MenuItem;
use App\Domain\GraphQL\Support\TypeRegistry;
use App\Domain\Themes\Support\RenderedPage;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * CMS-facing types (Page/Menu/Navigation) — mirrors
 * StorefrontPageController/StorefrontMenuController exactly.
 * `PageType.rendered` is deliberately a JSON scalar rather than a fully
 * modeled section/block schema: RenderedPage/RenderedSection are
 * already what REST returns verbatim via json_encode of their public
 * properties (Milestone 15's visual builder section/block tree is
 * recursive and merchant-defined, not a fixed shape a GraphQL object
 * type could usefully constrain) — see docs/adr/029-graphql-platform.md.
 */
final class CmsTypes
{
    public static function page(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'Page',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'title' => Type::nonNull(Type::string()),
                'slug' => Type::nonNull(Type::string()),
                'rendered' => $types->get('JSON'),
                'seo' => $types->get('Seo'),
            ],
        ]);
    }

    /**
     * @param  array{id: string, title: string, slug: string}  $page
     * @param  array{meta_title: ?string, meta_description: ?string, canonical_url: ?string, og_image: ?string}|null  $seo
     * @return array<string, mixed>
     */
    public static function pageArray(array $page, RenderedPage $rendered, ?array $seo): array
    {
        return [
            'id' => $page['id'],
            'title' => $page['title'],
            'slug' => $page['slug'],
            'rendered' => json_decode(json_encode($rendered), true),
            'seo' => $seo === null ? null : ['title' => $seo['meta_title'], 'description' => $seo['meta_description']],
        ];
    }

    public static function menuItem(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'MenuItem',
            'fields' => fn () => [
                'label' => Type::nonNull(Type::string()),
                'url' => Type::string(),
                'targetType' => Type::string(),
                'children' => Type::listOf($types->get('MenuItem')),
            ],
            'resolveField' => function (MenuItem $item, array $args, mixed $context, ResolveInfo $info) {
                return match ($info->fieldName) {
                    'label' => $item->label,
                    'url' => $item->url,
                    'targetType' => $item->target_type->value,
                    'children' => $item->relationLoaded('children') ? $item->children->all() : [],
                    default => null,
                };
            },
        ]);
    }

    public static function menu(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'Menu',
            'fields' => [
                'handle' => Type::nonNull(Type::string()),
                'items' => Type::listOf($types->get('MenuItem')),
            ],
        ]);
    }
}
