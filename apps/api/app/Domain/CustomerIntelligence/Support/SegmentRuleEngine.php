<?php

namespace App\Domain\CustomerIntelligence\Support;

use App\Domain\CustomerIntelligence\Enums\CustomerGroupType;
use App\Domain\CustomerIntelligence\Enums\SegmentRuleBoolean;
use App\Domain\CustomerIntelligence\Enums\SegmentRuleField;
use App\Domain\CustomerIntelligence\Models\CustomerGroup;
use App\Domain\CustomerIntelligence\Models\CustomerGroupMember;
use App\Domain\CustomerIntelligence\Models\SegmentRule;
use Illuminate\Support\Collection;

/**
 * Evaluates a SegmentRule tree against one customer (spec section 5: AND
 * / OR / nested groups / comparison / date / numeric / string / boolean
 * operators). The root is a flat list of top-level nodes, implicitly
 * ANDed — a dynamic CustomerGroup's rules are typically just a flat list
 * of condition nodes (no nesting needed); a CustomerSegment can nest
 * arbitrarily by making a root node itself a group node.
 *
 * `field: InGroup` resolves a manual group via CustomerGroupMember
 * directly, and a dynamic or protected group by recursively evaluating
 * that group's own rule tree with this same engine — deliberately kept
 * self-contained here (rather than delegating to SegmentMembership)
 * so this engine has no dependency on the facade that itself depends on
 * it. `$visitedGroupIds` guards against a group whose rules reference
 * itself (directly or transitively) recursing forever — SegmentMembership
 * also validates against this at write time, but a runtime guard is the
 * actual safety net.
 */
final class SegmentRuleEngine
{
    public function __construct(
        private readonly SegmentRuleFieldRegistry $fieldRegistry,
        private readonly SegmentRuleConditionEvaluator $conditionEvaluator,
    ) {}

    /**
     * @param  Collection<int, SegmentRule>  $rootRules
     * @param  array<int, string>  $visitedGroupIds
     */
    public function evaluate(SegmentEvaluationContext $context, Collection $rootRules, array $visitedGroupIds = []): bool
    {
        if ($rootRules->isEmpty()) {
            // No rules at all -> matches nobody, not everybody: an empty
            // segment/dynamic group is an unfinished one, not a
            // deliberate "everyone" wildcard.
            return false;
        }

        return $rootRules->every(fn (SegmentRule $rule) => $this->evaluateNode($context, $rule, $visitedGroupIds));
    }

    /**
     * @param  array<int, string>  $visitedGroupIds
     */
    private function evaluateNode(SegmentEvaluationContext $context, SegmentRule $rule, array $visitedGroupIds): bool
    {
        if ($rule->isGroup()) {
            $children = $rule->children;

            if ($children->isEmpty()) {
                return $rule->boolean_operator === SegmentRuleBoolean::And;
            }

            return $rule->boolean_operator === SegmentRuleBoolean::Or
                ? $children->contains(fn (SegmentRule $child) => $this->evaluateNode($context, $child, $visitedGroupIds))
                : $children->every(fn (SegmentRule $child) => $this->evaluateNode($context, $child, $visitedGroupIds));
        }

        if ($rule->field === SegmentRuleField::InGroup) {
            return $this->evaluateInGroup($context, $rule, $visitedGroupIds);
        }

        $actual = $this->fieldRegistry->resolve($rule->field, $context);

        return $this->conditionEvaluator->evaluate($actual, $rule->operator, $rule->value);
    }

    /**
     * @param  array<int, string>  $visitedGroupIds
     */
    private function evaluateInGroup(SegmentEvaluationContext $context, SegmentRule $rule, array $visitedGroupIds): bool
    {
        $groupIds = collect(is_array($rule->value) ? $rule->value : [$rule->value])->filter();

        foreach ($groupIds as $groupId) {
            $groupId = (string) $groupId;

            if (in_array($groupId, $visitedGroupIds, true)) {
                continue;
            }

            $group = CustomerGroup::query()->find($groupId);

            if ($group === null) {
                continue;
            }

            if ($group->type === CustomerGroupType::Manual) {
                $isMember = CustomerGroupMember::query()
                    ->where('customer_group_id', $group->id)
                    ->where('customer_id', $context->customer->id)
                    ->exists();
            } else {
                $isMember = $this->evaluate($context, $group->rootRules, [...$visitedGroupIds, $groupId]);
            }

            if ($isMember) {
                return true;
            }
        }

        return false;
    }
}
