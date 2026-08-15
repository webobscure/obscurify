<?php

namespace App\Domain\CustomerIntelligence\Enums;

/**
 * Every field a segment/group rule condition can test (spec section 4's
 * examples). Value type and the source of truth per case — see
 * SegmentRuleFieldRegistry for the actual resolver + which
 * SegmentRuleOperator cases are valid against it:
 *
 *  - TotalSpent/AverageOrderValue/LifetimeValue: integer, minor units — CustomerMetric.
 *  - OrderCount/RefundCount/ReturnCount: integer — CustomerMetric.
 *  - ReturnRate: integer, basis points (1/100 of a percent) — CustomerMetric.
 *  - DaysSinceLastOrder: integer, computed from CustomerMetric.last_order_at
 *    (a customer with no orders is treated as an unbounded number of days).
 *  - DaysSinceRegistration: integer, computed from Customer.created_at.
 *  - CountryCode: 2-letter string — the customer's default shipping
 *    CustomerAddress, falling back to their most recent Order's shipping
 *    address if no default is set.
 *  - EmailVerified: boolean — Customer.verified_at !== null.
 *  - DateOfBirth: date, month-only comparison via SegmentRuleOperator::ThisMonth — Customer.date_of_birth.
 *  - HasTag: string set membership — the tag's slug, against the
 *    customer's CustomerTagAssignments.
 *  - InGroup: string set membership — a CustomerGroup id, evaluated via
 *    SegmentMembership (manual: CustomerGroupMember row; dynamic/protected:
 *    that group's own rule tree, recursively).
 */
enum SegmentRuleField: string
{
    case TotalSpent = 'total_spent';
    case AverageOrderValue = 'average_order_value';
    case OrderCount = 'order_count';
    case RefundCount = 'refund_count';
    case ReturnCount = 'return_count';
    case ReturnRate = 'return_rate';
    case LifetimeValue = 'lifetime_value';
    case DaysSinceLastOrder = 'days_since_last_order';
    case DaysSinceRegistration = 'days_since_registration';
    case CountryCode = 'country_code';
    case EmailVerified = 'email_verified';
    case DateOfBirth = 'date_of_birth';
    case HasTag = 'has_tag';
    case InGroup = 'in_group';
}
