<?php

namespace App\Domain\CustomerIntelligence\Support;

use App\Domain\CustomerIntelligence\Enums\CustomerGroupType;
use App\Domain\CustomerIntelligence\Enums\SegmentRuleField;
use App\Domain\CustomerIntelligence\Enums\SegmentRuleOperator;
use App\Domain\CustomerIntelligence\Models\CustomerGroup;
use App\Domain\CustomerIntelligence\Models\CustomerGroupMember;
use App\Domain\CustomerIntelligence\Models\CustomerSegment;
use App\Domain\CustomerIntelligence\Models\CustomerTagAssignment;
use App\Domain\Customers\Models\Customer;

/**
 * The one public entry point another domain (Promotions, admin customer
 * search) should call to ask "is this customer in group/segment/tag X" —
 * spec section 9: "No direct SQL coupling." Nothing outside this domain
 * should query CustomerGroupMember/SegmentRule directly.
 */
final class SegmentMembership
{
    public function __construct(
        private readonly SegmentRuleEngine $ruleEngine,
        private readonly SegmentRuleFieldRegistry $fieldRegistry,
        private readonly SegmentRuleConditionEvaluator $conditionEvaluator,
    ) {}

    public function isCustomerInGroup(Customer $customer, CustomerGroup $group): bool
    {
        if ($group->type === CustomerGroupType::Manual) {
            return CustomerGroupMember::query()
                ->where('customer_group_id', $group->id)
                ->where('customer_id', $customer->id)
                ->exists();
        }

        return $this->ruleEngine->evaluate(SegmentEvaluationContext::build($customer), $group->rootRules);
    }

    public function isCustomerInSegment(Customer $customer, CustomerSegment $segment): bool
    {
        return $this->ruleEngine->evaluate(SegmentEvaluationContext::build($customer), $segment->rootRules);
    }

    public function customerHasTag(Customer $customer, string $tagSlug): bool
    {
        return CustomerTagAssignment::query()
            ->where('customer_id', $customer->id)
            ->whereHas('tag', fn ($query) => $query->where('slug', $tagSlug))
            ->exists();
    }

    /**
     * The id/nullable-customer-safe variants below exist for
     * Promotions\Support\RuleEngine, whose PromotionContext only ever
     * carries a `?string $customerId` (never a hydrated Customer) — see
     * that context's own docblock on why: it deliberately never queries
     * Customer directly, so a null id (an anonymous cart/guest checkout
     * with no identity yet) must resolve to "no match" rather than a
     * query error.
     *
     * @param  list<string>  $groupIds
     */
    public function isCustomerIdInAnyGroup(?string $customerId, array $groupIds): bool
    {
        $customer = $this->resolveCustomer($customerId);

        if ($customer === null) {
            return false;
        }

        $groups = CustomerGroup::query()->whereIn('id', $groupIds)->get();

        return $groups->contains(fn (CustomerGroup $group) => $this->isCustomerInGroup($customer, $group));
    }

    /**
     * @param  list<string>  $segmentIds
     */
    public function isCustomerIdInAnySegment(?string $customerId, array $segmentIds): bool
    {
        $customer = $this->resolveCustomer($customerId);

        if ($customer === null) {
            return false;
        }

        $segments = CustomerSegment::query()->whereIn('id', $segmentIds)->get();

        return $segments->contains(fn (CustomerSegment $segment) => $this->isCustomerInSegment($customer, $segment));
    }

    /**
     * @param  list<string>  $tagSlugs
     */
    public function customerIdHasAnyTag(?string $customerId, array $tagSlugs): bool
    {
        if ($customerId === null) {
            return false;
        }

        return CustomerTagAssignment::query()
            ->where('customer_id', $customerId)
            ->whereHas('tag', fn ($query) => $query->whereIn('slug', $tagSlugs))
            ->exists();
    }

    public function evaluateCustomerMetricCondition(?string $customerId, SegmentRuleField $field, SegmentRuleOperator $operator, mixed $value): bool
    {
        $customer = $this->resolveCustomer($customerId);

        if ($customer === null) {
            return false;
        }

        $context = SegmentEvaluationContext::build($customer);
        $actual = $this->fieldRegistry->resolve($field, $context);

        return $this->conditionEvaluator->evaluate($actual, $operator, $value);
    }

    private function resolveCustomer(?string $customerId): ?Customer
    {
        return $customerId === null ? null : Customer::query()->find($customerId);
    }
}
