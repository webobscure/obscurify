<?php

namespace App\Domain\CustomerIntelligence\Support;

use App\Domain\CustomerIntelligence\Enums\SegmentRuleOperator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Applies one SegmentRuleOperator to one resolved field value. Every
 * method is null-safe: an unresolvable field (e.g. no country on record)
 * simply fails every comparison rather than throwing, so one customer's
 * incomplete data can never break evaluating an entire segment.
 */
final class SegmentRuleConditionEvaluator
{
    public function evaluate(mixed $actual, SegmentRuleOperator $operator, mixed $expected): bool
    {
        return match ($operator) {
            SegmentRuleOperator::Equals => $this->compare($actual, $expected) === 0,
            SegmentRuleOperator::NotEquals => $actual !== null && $this->compare($actual, $expected) !== 0,
            SegmentRuleOperator::GreaterThan => $actual !== null && $this->compare($actual, $expected) > 0,
            SegmentRuleOperator::GreaterThanOrEqual => $actual !== null && $this->compare($actual, $expected) >= 0,
            SegmentRuleOperator::LessThan => $actual !== null && $this->compare($actual, $expected) < 0,
            SegmentRuleOperator::LessThanOrEqual => $actual !== null && $this->compare($actual, $expected) <= 0,
            SegmentRuleOperator::Contains => is_string($actual) && is_string($expected) && str_contains(mb_strtolower($actual), mb_strtolower($expected)),
            SegmentRuleOperator::StartsWith => is_string($actual) && is_string($expected) && str_starts_with(mb_strtolower($actual), mb_strtolower($expected)),
            SegmentRuleOperator::EndsWith => is_string($actual) && is_string($expected) && str_ends_with(mb_strtolower($actual), mb_strtolower($expected)),
            SegmentRuleOperator::IsTrue => $actual === true,
            SegmentRuleOperator::IsFalse => $actual === false,
            SegmentRuleOperator::InSet => $this->inSet($actual, $expected),
            SegmentRuleOperator::NotInSet => ! $this->inSet($actual, $expected),
            SegmentRuleOperator::ThisMonth => $this->isThisMonth($actual),
        };
    }

    private function compare(mixed $actual, mixed $expected): int
    {
        if (is_numeric($actual) && is_numeric($expected)) {
            return $actual <=> $expected;
        }

        return mb_strtolower((string) $actual) <=> mb_strtolower((string) $expected);
    }

    /**
     * $actual is either a single scalar (e.g. a country code) or a
     * Collection (e.g. tag slugs) — $expected is always the set of
     * values to test membership against, since a condition like "tag in
     * [VIP, Wholesale]" is the natural shape for this operator.
     */
    private function inSet(mixed $actual, mixed $expected): bool
    {
        $haystack = collect(is_array($expected) ? $expected : [$expected])
            ->map(fn ($v) => is_string($v) ? mb_strtolower($v) : $v);

        if ($actual instanceof Collection) {
            return $actual->contains(fn ($v) => $haystack->contains(is_string($v) ? mb_strtolower($v) : $v));
        }

        $needle = is_string($actual) ? mb_strtolower($actual) : $actual;

        return $haystack->contains($needle);
    }

    private function isThisMonth(mixed $actual): bool
    {
        if ($actual === null) {
            return false;
        }

        $date = $actual instanceof Carbon ? $actual : Carbon::parse((string) $actual);

        return $date->month === now()->month;
    }
}
