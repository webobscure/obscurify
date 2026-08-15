<?php

namespace App\Domain\Automation\Support;

use App\Domain\Automation\Enums\WorkflowConditionBoolean;
use App\Domain\Automation\Enums\WorkflowConditionOperator;
use App\Domain\Automation\Models\WorkflowCondition;
use App\Domain\CustomerIntelligence\Support\SegmentMembership;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Recursively evaluates a workflow's condition tree against an
 * execution's Context (spec section 4: AND/OR, nested groups, and the
 * various operator families). `variable_key` is a dot path into the
 * Context (e.g. "order.total_amount", "customer.email"); the six
 * Customer-Intelligence-aware operators (in_segment/in_group/has_tag and
 * their negations) instead read `customer.id` out of the Context and
 * delegate to SegmentMembership — M18's own facade, reused exactly as
 * Promotions already reuses it, so there is no direct coupling to
 * SegmentRuleEngine internals (see docs/adr/025-automation-engine.md).
 *
 * A condition tree with zero top-level nodes matches everyone — mirrors
 * SegmentRuleEngine's "no rules = matches nobody" being the one
 * deliberate asymmetry: an empty *trigger condition* means "always run,"
 * since a workflow with no conditions configured is a common, valid
 * "run on every trigger event" workflow.
 */
final class WorkflowConditionEvaluator
{
    public function __construct(private readonly SegmentMembership $segmentMembership) {}

    /**
     * @param  Collection<int, WorkflowCondition>  $rootConditions
     * @param  array<string, mixed>  $context
     */
    public function evaluate(Collection $rootConditions, array $context): bool
    {
        if ($rootConditions->isEmpty()) {
            return true;
        }

        return $rootConditions->every(fn (WorkflowCondition $condition) => $this->evaluateNode($condition, $context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function evaluateNode(WorkflowCondition $condition, array $context): bool
    {
        if ($condition->isGroup()) {
            $children = $condition->children;

            if ($children->isEmpty()) {
                return true;
            }

            return $condition->boolean_operator === WorkflowConditionBoolean::Or
                ? $children->some(fn (WorkflowCondition $child) => $this->evaluateNode($child, $context))
                : $children->every(fn (WorkflowCondition $child) => $this->evaluateNode($child, $context));
        }

        return $this->evaluateCondition($condition, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function evaluateCondition(WorkflowCondition $condition, array $context): bool
    {
        $operator = $condition->operator;

        if ($operator === null) {
            return false;
        }

        if ($this->isMembershipOperator($operator)) {
            return $this->evaluateMembership($operator, $condition->value, $context);
        }

        $actual = $condition->variable_key !== null ? Arr::get($context, $condition->variable_key) : null;

        return $this->compare($actual, $operator, $condition->value);
    }

    private function isMembershipOperator(WorkflowConditionOperator $operator): bool
    {
        return in_array($operator, [
            WorkflowConditionOperator::InSegment,
            WorkflowConditionOperator::NotInSegment,
            WorkflowConditionOperator::InGroup,
            WorkflowConditionOperator::NotInGroup,
            WorkflowConditionOperator::HasTag,
            WorkflowConditionOperator::NotHasTag,
        ], true);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function evaluateMembership(WorkflowConditionOperator $operator, mixed $value, array $context): bool
    {
        $customerId = Arr::get($context, 'customer.id');

        if (! is_string($customerId)) {
            return false;
        }

        $target = is_string($value) ? $value : null;

        if ($target === null) {
            return false;
        }

        return match ($operator) {
            WorkflowConditionOperator::InSegment => $this->segmentMembership->isCustomerIdInAnySegment($customerId, [$target]),
            WorkflowConditionOperator::NotInSegment => ! $this->segmentMembership->isCustomerIdInAnySegment($customerId, [$target]),
            WorkflowConditionOperator::InGroup => $this->segmentMembership->isCustomerIdInAnyGroup($customerId, [$target]),
            WorkflowConditionOperator::NotInGroup => ! $this->segmentMembership->isCustomerIdInAnyGroup($customerId, [$target]),
            WorkflowConditionOperator::HasTag => $this->segmentMembership->customerIdHasAnyTag($customerId, [$target]),
            WorkflowConditionOperator::NotHasTag => ! $this->segmentMembership->customerIdHasAnyTag($customerId, [$target]),
            default => false,
        };
    }

    private function compare(mixed $actual, WorkflowConditionOperator $operator, mixed $expected): bool
    {
        return match ($operator) {
            WorkflowConditionOperator::Equals => $this->looseEquals($actual, $expected),
            WorkflowConditionOperator::NotEquals => ! $this->looseEquals($actual, $expected),
            WorkflowConditionOperator::GreaterThan => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            WorkflowConditionOperator::GreaterThanOrEqual => is_numeric($actual) && is_numeric($expected) && $actual >= $expected,
            WorkflowConditionOperator::LessThan => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            WorkflowConditionOperator::LessThanOrEqual => is_numeric($actual) && is_numeric($expected) && $actual <= $expected,
            WorkflowConditionOperator::Contains => is_string($actual) && is_string($expected) && Str::contains($actual, $expected),
            WorkflowConditionOperator::StartsWith => is_string($actual) && is_string($expected) && Str::startsWith($actual, $expected),
            WorkflowConditionOperator::EndsWith => is_string($actual) && is_string($expected) && Str::endsWith($actual, $expected),
            WorkflowConditionOperator::IsTrue => $actual === true,
            WorkflowConditionOperator::IsFalse => $actual === false,
            WorkflowConditionOperator::InSet => is_array($expected) && in_array($actual, $expected, false),
            WorkflowConditionOperator::NotInSet => is_array($expected) && ! in_array($actual, $expected, false),
            default => false,
        };
    }

    private function looseEquals(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual) || is_array($expected)) {
            return false;
        }

        if (is_bool($actual) || is_bool($expected)) {
            return (bool) $actual === (bool) $expected;
        }

        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        return (string) $actual === (string) $expected;
    }
}
