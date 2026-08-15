<?php

use App\Domain\CustomerIntelligence\Enums\CustomerGroupType;
use App\Domain\CustomerIntelligence\Enums\SegmentableType;
use App\Domain\CustomerIntelligence\Enums\SegmentRuleBoolean;
use App\Domain\CustomerIntelligence\Enums\SegmentRuleField;
use App\Domain\CustomerIntelligence\Enums\SegmentRuleOperator;
use App\Domain\CustomerIntelligence\Models\CustomerGroup;
use App\Domain\CustomerIntelligence\Models\CustomerGroupMember;
use App\Domain\CustomerIntelligence\Models\CustomerMetric;
use App\Domain\CustomerIntelligence\Models\SegmentRule;
use App\Domain\CustomerIntelligence\Support\SegmentEvaluationContext;
use App\Domain\CustomerIntelligence\Support\SegmentRuleEngine;
use App\Domain\Customers\Models\Customer;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);

    [$this->customer, $this->metric] = app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create(['created_at' => now()->subDays(400)]);

        $metric = CustomerMetric::query()->create([
            'customer_id' => $customer->id,
            'total_spent_amount' => 60000,
            'average_order_value_amount' => 20000,
            'order_count' => 3,
            'refund_count' => 0,
            'return_count' => 1,
            'return_rate_bps' => 3333,
            'lifetime_value_amount' => 60000,
            'currency' => 'USD',
            'first_order_at' => now()->subDays(300),
            'last_order_at' => now()->subDays(10),
            'computed_at' => now(),
        ]);

        return [$customer, $metric];
    });
});

function makeCondition(string $field, string $operator, mixed $value): SegmentRule
{
    return SegmentRule::query()->create([
        'segmentable_type' => SegmentableType::CustomerSegment->value,
        'segmentable_id' => (string) Str::ulid(),
        'field' => $field,
        'operator' => $operator,
        'value' => $value,
        'position' => 0,
    ]);
}

it('evaluates a single numeric condition', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $engine = app(SegmentRuleEngine::class);
        $context = SegmentEvaluationContext::build($this->customer, $this->metric);

        $rule = makeCondition(SegmentRuleField::TotalSpent->value, SegmentRuleOperator::GreaterThan->value, 50000);
        expect($engine->evaluate($context, collect([$rule])))->toBeTrue();

        $rule2 = makeCondition(SegmentRuleField::TotalSpent->value, SegmentRuleOperator::GreaterThan->value, 70000);
        expect($engine->evaluate($context, collect([$rule2])))->toBeFalse();
    });
});

it('ANDs multiple top-level conditions', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $engine = app(SegmentRuleEngine::class);
        $context = SegmentEvaluationContext::build($this->customer, $this->metric);

        $rules = collect([
            makeCondition(SegmentRuleField::OrderCount->value, SegmentRuleOperator::GreaterThanOrEqual->value, 3),
            makeCondition(SegmentRuleField::TotalSpent->value, SegmentRuleOperator::GreaterThan->value, 50000),
        ]);

        expect($engine->evaluate($context, $rules))->toBeTrue();

        $failing = collect([
            makeCondition(SegmentRuleField::OrderCount->value, SegmentRuleOperator::GreaterThanOrEqual->value, 3),
            makeCondition(SegmentRuleField::TotalSpent->value, SegmentRuleOperator::GreaterThan->value, 90000),
        ]);

        expect($engine->evaluate($context, $failing))->toBeFalse();
    });
});

it('evaluates a nested OR group inside an AND', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $engine = app(SegmentRuleEngine::class);
        $context = SegmentEvaluationContext::build($this->customer, $this->metric);

        $orGroup = SegmentRule::query()->create([
            'segmentable_type' => SegmentableType::CustomerSegment->value,
            'segmentable_id' => (string) Str::ulid(),
            'boolean_operator' => SegmentRuleBoolean::Or->value,
            'position' => 0,
        ]);

        SegmentRule::query()->create([
            'segmentable_type' => SegmentableType::CustomerSegment->value,
            'segmentable_id' => (string) Str::ulid(),
            'parent_id' => $orGroup->id,
            'field' => SegmentRuleField::CountryCode->value,
            'operator' => SegmentRuleOperator::Equals->value,
            'value' => 'SE',
            'position' => 0,
        ]);

        SegmentRule::query()->create([
            'segmentable_type' => SegmentableType::CustomerSegment->value,
            'segmentable_id' => (string) Str::ulid(),
            'parent_id' => $orGroup->id,
            'field' => SegmentRuleField::OrderCount->value,
            'operator' => SegmentRuleOperator::GreaterThanOrEqual->value,
            'value' => 3,
            'position' => 1,
        ]);

        $topLevelAnd = makeCondition(SegmentRuleField::TotalSpent->value, SegmentRuleOperator::GreaterThan->value, 1);

        // Country doesn't match ('SE') but order_count >= 3 does — OR passes.
        expect($engine->evaluate($context, collect([$topLevelAnd, $orGroup->fresh(['children'])])))->toBeTrue();
    });
});

it('evaluates boolean, string, and date-month operators', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $engine = app(SegmentRuleEngine::class);

        $customer = Customer::factory()->create([
            'verified_at' => now(),
            'date_of_birth' => now()->subYears(30)->startOfMonth(),
        ]);
        $context = SegmentEvaluationContext::build($customer);

        $verified = makeCondition(SegmentRuleField::EmailVerified->value, SegmentRuleOperator::IsTrue->value, null);
        expect($engine->evaluate($context, collect([$verified])))->toBeTrue();

        $birthdayThisMonth = makeCondition(SegmentRuleField::DateOfBirth->value, SegmentRuleOperator::ThisMonth->value, null);
        expect($engine->evaluate($context, collect([$birthdayThisMonth])))->toBeTrue();

        $notVerifiedCustomer = Customer::factory()->create(['verified_at' => null]);
        $unverifiedContext = SegmentEvaluationContext::build($notVerifiedCustomer);
        expect($engine->evaluate($unverifiedContext, collect([$verified])))->toBeFalse();
    });
});

it('resolves InGroup against a manual group via CustomerGroupMember', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $engine = app(SegmentRuleEngine::class);
        $context = SegmentEvaluationContext::build($this->customer, $this->metric);

        $group = CustomerGroup::factory()->create(['type' => CustomerGroupType::Manual->value]);

        $inGroupBefore = makeCondition(SegmentRuleField::InGroup->value, SegmentRuleOperator::InSet->value, [$group->id]);
        expect($engine->evaluate($context, collect([$inGroupBefore])))->toBeFalse();

        CustomerGroupMember::query()->create([
            'customer_group_id' => $group->id,
            'customer_id' => $this->customer->id,
            'assigned_at' => now(),
        ]);

        $inGroupAfter = makeCondition(SegmentRuleField::InGroup->value, SegmentRuleOperator::InSet->value, [$group->id]);
        expect($engine->evaluate($context, collect([$inGroupAfter])))->toBeTrue();
    });
});

it('an empty rule list matches nobody', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $engine = app(SegmentRuleEngine::class);
        $context = SegmentEvaluationContext::build($this->customer, $this->metric);

        expect($engine->evaluate($context, collect()))->toBeFalse();
    });
});

it('a group referencing itself does not infinitely recurse', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $engine = app(SegmentRuleEngine::class);
        $context = SegmentEvaluationContext::build($this->customer, $this->metric);

        $group = CustomerGroup::factory()->dynamic()->create();

        SegmentRule::query()->create([
            'segmentable_type' => SegmentableType::CustomerGroup->value,
            'segmentable_id' => $group->id,
            'field' => SegmentRuleField::InGroup->value,
            'operator' => SegmentRuleOperator::InSet->value,
            'value' => [$group->id],
            'position' => 0,
        ]);

        $selfReferencing = makeCondition(SegmentRuleField::InGroup->value, SegmentRuleOperator::InSet->value, [$group->id]);

        // Must terminate (the whole point of the test) and resolve to
        // false, since a self-referencing definition can never actually
        // be satisfied by anything.
        expect($engine->evaluate($context, collect([$selfReferencing])))->toBeFalse();
    });
});
