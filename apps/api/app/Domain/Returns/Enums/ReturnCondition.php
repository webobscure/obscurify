<?php

namespace App\Domain\Returns\Enums;

/**
 * Shared by ReturnItem.condition (the customer/merchant's own claim at
 * request time, unverified) and ReturnInspection.condition (the
 * merchant's verified assessment after physically examining the item) —
 * same vocabulary, two different points of trust.
 */
enum ReturnCondition: string
{
    case New = 'new';
    case LikeNew = 'like_new';
    case Damaged = 'damaged';
    case Defective = 'defective';
    case MissingParts = 'missing_parts';
    case Other = 'other';
}
