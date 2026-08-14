<?php

namespace App\Domain\Cms\Enums;

/**
 * What a MenuItem links to. `Url` is an arbitrary hand-typed link (the
 * item's own `url` column); every other case resolves `target_id`
 * against the matching table at render time.
 */
enum MenuItemTargetType: string
{
    case Url = 'url';
    case Page = 'page';
    case Collection = 'collection';
    case Product = 'product';
    case Blog = 'blog';
    case BlogPost = 'blog_post';
}
