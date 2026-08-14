<?php

namespace App\Domain\Cms\Enums;

/**
 * What a SeoMetadata row describes. Only `PageVersion` and `BlogPost`
 * exist today; `Product`/`Collection` are natural future additions (see
 * the migration's docblock) — a new case, not a schema change.
 */
enum SeoSubjectType: string
{
    case PageVersion = 'page_version';
    case BlogPost = 'blog_post';
}
