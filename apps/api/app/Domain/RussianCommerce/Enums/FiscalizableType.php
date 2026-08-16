<?php

namespace App\Domain\RussianCommerce\Enums;

/**
 * Closed set of entities a ProductFiscalProfile can attach to — mirrors
 * MediaEntityType's identical Product/ProductVariant polymorphic
 * pattern exactly.
 */
enum FiscalizableType: string
{
    case Product = 'product';
    case ProductVariant = 'product_variant';
}
