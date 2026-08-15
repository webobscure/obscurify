<?php

namespace App\Domain\CustomerIntelligence\Enums;

/**
 * Only set on a *group* SegmentRule node — combines that node's children
 * (spec section 5: AND / OR / nested groups). Null on a condition node.
 */
enum SegmentRuleBoolean: string
{
    case And = 'and';
    case Or = 'or';
}
