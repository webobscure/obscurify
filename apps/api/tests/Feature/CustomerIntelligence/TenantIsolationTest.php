<?php

use App\Domain\CustomerIntelligence\Application\CreateCustomerGroup;
use App\Domain\CustomerIntelligence\Application\CreateCustomerSegment;
use App\Domain\CustomerIntelligence\Application\CreateCustomerTag;
use App\Domain\CustomerIntelligence\Application\RecomputeCustomerMetrics;
use App\Domain\Customers\Models\Customer;
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
 * Spec section 14: Store A cannot access Store B's segments/groups/
 * metrics/tags.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->storeA = createStoreForUser($this->user, ['slug' => 'ci-tenant-a']);
    $this->storeB = createStoreForUser($this->user, ['slug' => 'ci-tenant-b']);
});

it('never returns another stores customer group, segment, or tag by id', function () {
    [$groupB, $segmentB, $tagB] = app(TenantContext::class)->scope($this->storeB, function () {
        return [
            app(CreateCustomerGroup::class)->handle(['name' => 'B Wholesale', 'type' => 'manual']),
            app(CreateCustomerSegment::class)->handle(['name' => 'B Segment']),
            app(CreateCustomerTag::class)->handle(['name' => 'B Tag']),
        ];
    });

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/customer-groups/{$groupB->id}", tenantHeader($this->storeA))
        ->assertStatus(404);

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/customer-segments/{$segmentB->id}", tenantHeader($this->storeA))
        ->assertStatus(404);

    // customer-tags has no single-resource GET; deletion is the
    // write-path proof that Store A's tenant scope can't resolve it.
    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/customer-tags/{$tagB->id}", [], tenantHeader($this->storeA))
        ->assertStatus(404);
});

it('never lists another stores groups/segments/tags in the index endpoints', function () {
    app(TenantContext::class)->scope($this->storeB, function () {
        app(CreateCustomerGroup::class)->handle(['name' => 'B Only Group', 'type' => 'manual']);
        app(CreateCustomerSegment::class)->handle(['name' => 'B Only Segment']);
        app(CreateCustomerTag::class)->handle(['name' => 'B Only Tag']);
    });

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/customer-groups', tenantHeader($this->storeA))
        ->assertOk()->assertJsonCount(0, 'data');

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/customer-segments', tenantHeader($this->storeA))
        ->assertOk()->assertJsonCount(0, 'data');

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/customer-tags', tenantHeader($this->storeA))
        ->assertOk()->assertJsonCount(0, 'data');
});

it('never returns another stores customer metrics/groups/segments via the admin customer detail endpoints', function () {
    $customerBId = app(TenantContext::class)->scope($this->storeB, function () {
        $customer = Customer::factory()->create();
        app(RecomputeCustomerMetrics::class)->handle($customer->id);

        return $customer->id;
    });

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/customers/{$customerBId}/metrics", tenantHeader($this->storeA))
        ->assertStatus(404);

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/customers/{$customerBId}/groups", tenantHeader($this->storeA))
        ->assertStatus(404);

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/customers/{$customerBId}/segments", tenantHeader($this->storeA))
        ->assertStatus(404);
});

it('a promotion in Store A cannot target a segment that only exists in Store B', function () {
    $segmentB = app(TenantContext::class)->scope($this->storeB, fn () => app(CreateCustomerSegment::class)->handle(['name' => 'B Segment']));

    $customerA = app(TenantContext::class)->scope($this->storeA, fn () => Customer::factory()->create());

    app(TenantContext::class)->scope($this->storeA, function () use ($segmentB, $customerA) {
        $promotion = Promotion::factory()->create(['name' => 'Cross-tenant']);
        PromotionRule::query()->create([
            'promotion_id' => $promotion->id,
            'type' => PromotionRuleType::CustomerSegment->value,
            'parameters' => ['segment_ids' => [$segmentB->id]],
        ]);
        PromotionAction::query()->create([
            'promotion_id' => $promotion->id,
            'type' => PromotionActionType::PercentageOff->value,
            'parameters' => ['percent' => 10],
        ]);

        $line = new PromotionLine('product-1', 'variant-1', 1, 10000, [], []);
        $context = new PromotionContext(
            lines: [$line], itemsSubtotal: 10000, shippingAmount: 0, currency: 'USD',
            countryCode: null, customerId: $customerA->id, customerEmail: null,
            appliedDiscountCode: null, now: now(),
        );

        // SegmentMembership's CustomerSegment::query()->whereIn('id', ...)
        // is itself BelongsToTenant-scoped to Store A, so the Store-B
        // segment id simply never resolves to anything — no discount.
        expect(app(PromotionEngine::class)->handle($context)->discountAmount)->toBe(0);
    });
});
