<?php

namespace App\Domain\Automation\Enums;

/**
 * Comparison/string/set operators mirror SegmentRuleOperator (M18); the
 * four Customer-Intelligence-aware operators
 * (in_segment/in_group/has_tag and their negations) are what spec
 * section 4's "Customer Segment / Customer Group / Tags" condition
 * types compile down to, resolved through the same SegmentMembership
 * facade Promotions already uses — see WorkflowConditionEvaluator.
 */
enum WorkflowConditionOperator: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case GreaterThan = 'greater_than';
    case GreaterThanOrEqual = 'greater_than_or_equal';
    case LessThan = 'less_than';
    case LessThanOrEqual = 'less_than_or_equal';
    case Contains = 'contains';
    case StartsWith = 'starts_with';
    case EndsWith = 'ends_with';
    case IsTrue = 'is_true';
    case IsFalse = 'is_false';
    case InSet = 'in_set';
    case NotInSet = 'not_in_set';
    case InSegment = 'in_segment';
    case NotInSegment = 'not_in_segment';
    case InGroup = 'in_group';
    case NotInGroup = 'not_in_group';
    case HasTag = 'has_tag';
    case NotHasTag = 'not_has_tag';
}
