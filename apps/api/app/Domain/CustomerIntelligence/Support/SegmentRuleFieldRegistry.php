<?php

namespace App\Domain\CustomerIntelligence\Support;

use App\Domain\CustomerIntelligence\Enums\SegmentRuleField;

/**
 * Resolves a SegmentRuleField to its raw value for one customer — see
 * that enum's docblock for the exact definition/source of each case.
 * `InGroup` is deliberately not resolved here: it needs SegmentMembership
 * (which itself may need to evaluate another group's rule tree), so
 * SegmentRuleEngine special-cases it directly rather than routing it
 * through this registry, to keep this class free of that circular
 * dependency.
 */
final class SegmentRuleFieldRegistry
{
    public function resolve(SegmentRuleField $field, SegmentEvaluationContext $context): mixed
    {
        $customer = $context->customer;
        $metric = $context->metric;

        // A customer RecomputeCustomerMetrics has never run for (no
        // order, registration, or profile update yet) has no
        // CustomerMetric row at all — every metric-derived field reads
        // as its zero value in that case, exactly as if a row of zeros
        // existed, rather than needing a null-check at every call site.
        if ($metric === null) {
            return match ($field) {
                SegmentRuleField::TotalSpent, SegmentRuleField::AverageOrderValue, SegmentRuleField::OrderCount,
                SegmentRuleField::RefundCount, SegmentRuleField::ReturnCount, SegmentRuleField::ReturnRate,
                SegmentRuleField::LifetimeValue => 0,
                SegmentRuleField::DaysSinceLastOrder => PHP_INT_MAX,
                SegmentRuleField::DaysSinceRegistration => $customer->created_at->diffInDays(now()),
                SegmentRuleField::CountryCode => $context->countryCode(),
                SegmentRuleField::EmailVerified => $customer->isVerified(),
                SegmentRuleField::DateOfBirth => $customer->date_of_birth,
                SegmentRuleField::HasTag => $context->tagSlugs(),
                SegmentRuleField::InGroup => null,
            };
        }

        return match ($field) {
            SegmentRuleField::TotalSpent => $metric->total_spent_amount,
            SegmentRuleField::AverageOrderValue => $metric->average_order_value_amount,
            SegmentRuleField::OrderCount => $metric->order_count,
            SegmentRuleField::RefundCount => $metric->refund_count,
            SegmentRuleField::ReturnCount => $metric->return_count,
            // Basis points -> percentage points, the unit a merchant
            // actually types into the segment builder (e.g. "> 20").
            SegmentRuleField::ReturnRate => $metric->return_rate_bps / 100,
            SegmentRuleField::LifetimeValue => $metric->lifetime_value_amount,
            // No orders at all -> treated as an unbounded number of days,
            // so "no order in 90 days" correctly includes a customer who
            // has never ordered.
            SegmentRuleField::DaysSinceLastOrder => $metric->last_order_at === null ? PHP_INT_MAX : $metric->daysSinceLastOrder(),
            SegmentRuleField::DaysSinceRegistration => $customer->created_at->diffInDays(now()),
            SegmentRuleField::CountryCode => $context->countryCode(),
            SegmentRuleField::EmailVerified => $customer->isVerified(),
            SegmentRuleField::DateOfBirth => $customer->date_of_birth,
            SegmentRuleField::HasTag => $context->tagSlugs(),
            SegmentRuleField::InGroup => null,
        };
    }
}
