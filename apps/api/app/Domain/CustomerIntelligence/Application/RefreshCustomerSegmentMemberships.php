<?php

namespace App\Domain\CustomerIntelligence\Application;

use App\Domain\CustomerIntelligence\Enums\CustomerGroupStatus;
use App\Domain\CustomerIntelligence\Enums\CustomerGroupType;
use App\Domain\CustomerIntelligence\Enums\CustomerSegmentStatus;
use App\Domain\CustomerIntelligence\Models\CustomerGroup;
use App\Domain\CustomerIntelligence\Models\CustomerMetric;
use App\Domain\CustomerIntelligence\Models\CustomerSegment;
use App\Domain\CustomerIntelligence\Models\CustomerSegmentMembership;
use App\Domain\CustomerIntelligence\Support\SegmentEvaluationContext;
use App\Domain\CustomerIntelligence\Support\SegmentRuleEngine;
use App\Domain\Customers\Models\Customer;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Collection;

/**
 * Re-evaluates every dynamic/protected CustomerGroup and every
 * CustomerSegment in the store against one customer, and fires
 * CustomerEnteredSegment/CustomerLeftSegment (spec section 11) for
 * whichever ones changed — see the customer_segment_memberships
 * migration for why a row's presence *is* the membership state, and
 * docs/architecture/technical-debt entries for the known cost of
 * evaluating every segment on every recompute rather than only the ones
 * that could plausibly have changed.
 */
final class RefreshCustomerSegmentMemberships
{
    public function __construct(
        private readonly SegmentRuleEngine $ruleEngine,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    public function handle(Customer $customer, CustomerMetric $metric): void
    {
        $context = SegmentEvaluationContext::build($customer, $metric);

        $currentlyMember = CustomerSegmentMembership::query()
            ->where('customer_id', $customer->id)
            ->get()
            ->keyBy(fn (CustomerSegmentMembership $m) => "{$m->segmentable_type}:{$m->segmentable_id}");

        $shouldBeMember = [];

        foreach ($this->dynamicGroups() as $group) {
            $shouldBeMember["CustomerGroup:{$group->id}"] = $this->ruleEngine->evaluate($context, $group->rootRules);
        }

        foreach ($this->activeSegments() as $segment) {
            $shouldBeMember["CustomerSegment:{$segment->id}"] = $this->ruleEngine->evaluate($context, $segment->rootRules);
        }

        foreach ($shouldBeMember as $key => $isMember) {
            [$segmentableType, $segmentableId] = explode(':', $key, 2);
            $wasMember = $currentlyMember->has($key);

            if ($isMember && ! $wasMember) {
                CustomerSegmentMembership::query()->create([
                    'customer_id' => $customer->id,
                    'segmentable_type' => $segmentableType,
                    'segmentable_id' => $segmentableId,
                    'entered_at' => now(),
                ]);

                $this->recordOutboxEvent->handle('CustomerEnteredSegment', 'Customer', $customer->id, [
                    'customer_id' => $customer->id,
                    'store_id' => $customer->store_id,
                    'segmentable_type' => $segmentableType,
                    'segmentable_id' => $segmentableId,
                ]);
            } elseif (! $isMember && $wasMember) {
                $currentlyMember->get($key)->delete();

                $this->recordOutboxEvent->handle('CustomerLeftSegment', 'Customer', $customer->id, [
                    'customer_id' => $customer->id,
                    'store_id' => $customer->store_id,
                    'segmentable_type' => $segmentableType,
                    'segmentable_id' => $segmentableId,
                ]);
            }
        }
    }

    /**
     * @return Collection<int, CustomerGroup>
     */
    private function dynamicGroups()
    {
        return CustomerGroup::query()
            ->where('status', CustomerGroupStatus::Active->value)
            ->whereIn('type', [CustomerGroupType::Dynamic->value, CustomerGroupType::Protected->value])
            ->with('rootRules.children')
            ->get();
    }

    /**
     * @return Collection<int, CustomerSegment>
     */
    private function activeSegments()
    {
        return CustomerSegment::query()
            ->where('status', CustomerSegmentStatus::Active->value)
            ->with('rootRules.children')
            ->get();
    }
}
