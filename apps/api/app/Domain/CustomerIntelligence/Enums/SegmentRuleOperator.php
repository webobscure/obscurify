<?php

namespace App\Domain\CustomerIntelligence\Enums;

/**
 * Which operators are valid for which SegmentRuleField's value type is
 * enforced by SegmentRuleFieldRegistry, not here — this enum is just the
 * fixed vocabulary (spec section 5: comparison, date, numeric, string,
 * boolean operators).
 */
enum SegmentRuleOperator: string
{
    // Comparison / numeric / date (dates compare as day-granularity timestamps).
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case GreaterThan = 'greater_than';
    case GreaterThanOrEqual = 'greater_than_or_equal';
    case LessThan = 'less_than';
    case LessThanOrEqual = 'less_than_or_equal';

    // String.
    case Contains = 'contains';
    case StartsWith = 'starts_with';
    case EndsWith = 'ends_with';

    // Boolean.
    case IsTrue = 'is_true';
    case IsFalse = 'is_false';

    // Set membership (tag/group fields).
    case InSet = 'in_set';
    case NotInSet = 'not_in_set';

    // Date, month-of-year only (ignores the year) — backs "Birthday this month".
    case ThisMonth = 'this_month';
}
