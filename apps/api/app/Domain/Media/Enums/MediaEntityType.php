<?php

namespace App\Domain\Media\Enums;

/**
 * Closed set of entities media can attach to. Deliberately not a raw
 * Eloquent class name — see the `media` table migration for why.
 */
enum MediaEntityType: string
{
    case Product = 'product';
    case ProductVariant = 'product_variant';
}
