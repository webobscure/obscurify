<?php

use App\Domain\CustomerIntelligence\Application\AssignCustomerTag;
use App\Domain\CustomerIntelligence\Application\CreateCustomerGroup;
use App\Domain\CustomerIntelligence\Application\CreateCustomerSegment;
use App\Domain\CustomerIntelligence\Application\CreateCustomerTag;
use App\Domain\CustomerIntelligence\Application\RecomputeCustomerMetrics;
use App\Domain\CustomerIntelligence\Enums\SegmentRuleField;
use App\Domain\CustomerIntelligence\Enums\SegmentRuleOperator;
use App\Domain\CustomerIntelligence\Models\CustomerGroupMember;
use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Promotions\Enums\PromotionActionType;
use App\Domain\Promotions\Enums\PromotionRuleType;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\PromotionAction;
use App\Domain\Promotions\Models\PromotionRule;
use App\Domain\Promotions\Support\PromotionContext;
use App\Domain\Promotions\Support\PromotionEngine;
use App\Domain\Promotions\Support\PromotionLine;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

/**
 * Spec section 9: "Promotion Engine should be able to target Groups,
 * Segments, Tags, Metrics. No direct SQL coupling." — proves the four
 * new PromotionRuleType cases actually gate discount application, via
 * the real PromotionEngine/RuleEngine, never a raw query against
 * CustomerIntelligence's tables from the Promotions domain.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

function contextFor(?string $customerId): PromotionContext
{
    $line = new PromotionLine('product-1', 'variant-1', 1, 10000, [], []);

    return new PromotionContext(
        lines: [$line],
        itemsSubtotal: 10000,
        shippingAmount: 0,
        currency: 'USD',
        countryCode: null,
        customerId: $customerId,
        customerEmail: null,
        appliedDiscountCode: null,
        now: now(),
    );
}

it('targets a CustomerSegment', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $segment = app(CreateCustomerSegment::class)->handle([
            'name' => 'Big Spenders',
            'rules' => [['field' => SegmentRuleField::TotalSpent->value, 'operator' => SegmentRuleOperator::GreaterThan->value, 'value' => 5000]],
        ]);

        $qualifying = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $qualifying->id, 'total_amount' => 10000]);
        Payment::query()->create([
            'order_id' => $order->id, 'provider' => 'fake', 'status' => PaymentStatus::Paid->value,
            'currency' => 'USD', 'amount' => 10000, 'authorized_amount' => 10000, 'captured_amount' => 10000,
            'refunded_amount' => 0, 'external_payment_id' => 'p1',
        ]);
        app(RecomputeCustomerMetrics::class)->handle($qualifying->id);

        $nonQualifying = Customer::factory()->create();
        app(RecomputeCustomerMetrics::class)->handle($nonQualifying->id);

        $promotion = Promotion::factory()->create(['name' => '10% off for big spenders']);
        PromotionRule::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionRuleType::CustomerSegment->value, 'parameters' => ['segment_ids' => [$segment->id]]]);
        PromotionAction::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionActionType::PercentageOff->value, 'parameters' => ['percent' => 10]]);

        $engine = app(PromotionEngine::class);

        expect($engine->handle(contextFor($qualifying->id))->discountAmount)->toBe(1000);
        expect($engine->handle(contextFor($nonQualifying->id))->discountAmount)->toBe(0);
        expect($engine->handle(contextFor(null))->discountAmount)->toBe(0);
    });
});

it('targets a CustomerGroup', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $group = app(CreateCustomerGroup::class)->handle(['name' => 'Wholesale', 'type' => 'manual']);
        $member = Customer::factory()->create();
        CustomerGroupMember::query()->create(['customer_group_id' => $group->id, 'customer_id' => $member->id, 'assigned_at' => now()]);
        $nonMember = Customer::factory()->create();

        $promotion = Promotion::factory()->create(['name' => 'Wholesale discount']);
        PromotionRule::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionRuleType::CustomerGroup->value, 'parameters' => ['group_ids' => [$group->id]]]);
        PromotionAction::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionActionType::PercentageOff->value, 'parameters' => ['percent' => 15]]);

        $engine = app(PromotionEngine::class);

        expect($engine->handle(contextFor($member->id))->discountAmount)->toBe(1500);
        expect($engine->handle(contextFor($nonMember->id))->discountAmount)->toBe(0);
    });
});

it('targets a CustomerTag', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $tag = app(CreateCustomerTag::class)->handle(['name' => 'Influencer']);
        $tagged = Customer::factory()->create();
        app(AssignCustomerTag::class)->handle($tagged, $tag);
        $untagged = Customer::factory()->create();

        $promotion = Promotion::factory()->create(['name' => 'Influencer discount']);
        PromotionRule::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionRuleType::CustomerTag->value, 'parameters' => ['tag_slugs' => [$tag->slug]]]);
        PromotionAction::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionActionType::PercentageOff->value, 'parameters' => ['percent' => 20]]);

        $engine = app(PromotionEngine::class);

        expect($engine->handle(contextFor($tagged->id))->discountAmount)->toBe(2000);
        expect($engine->handle(contextFor($untagged->id))->discountAmount)->toBe(0);
    });
});

it('targets a CustomerMetric condition directly, reusing the segment rule vocabulary', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $highOrderCount = Customer::factory()->create();
        foreach (range(1, 6) as $i) {
            $order = Order::factory()->create(['customer_id' => $highOrderCount->id, 'total_amount' => 1000]);
            Payment::query()->create([
                'order_id' => $order->id, 'provider' => 'fake', 'status' => PaymentStatus::Paid->value,
                'currency' => 'USD', 'amount' => 1000, 'authorized_amount' => 1000, 'captured_amount' => 1000,
                'refunded_amount' => 0, 'external_payment_id' => "p{$i}",
            ]);
        }
        app(RecomputeCustomerMetrics::class)->handle($highOrderCount->id);

        $lowOrderCount = Customer::factory()->create();
        app(RecomputeCustomerMetrics::class)->handle($lowOrderCount->id);

        $promotion = Promotion::factory()->create(['name' => 'Loyalty discount']);
        PromotionRule::query()->create([
            'promotion_id' => $promotion->id,
            'type' => PromotionRuleType::CustomerMetric->value,
            'parameters' => ['field' => SegmentRuleField::OrderCount->value, 'operator' => SegmentRuleOperator::GreaterThan->value, 'value' => 5],
        ]);
        PromotionAction::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionActionType::PercentageOff->value, 'parameters' => ['percent' => 5]]);

        $engine = app(PromotionEngine::class);

        expect($engine->handle(contextFor($highOrderCount->id))->discountAmount)->toBe(500);
        expect($engine->handle(contextFor($lowOrderCount->id))->discountAmount)->toBe(0);
    });
});
