<?php

use App\Domain\Orders\Models\Order;
use App\Domain\Promotions\Enums\DiscountApplicationTarget as Target;
use App\Domain\Promotions\Enums\DiscountCodeStatus;
use App\Domain\Promotions\Enums\PromotionActionType;
use App\Domain\Promotions\Enums\PromotionRuleType;
use App\Domain\Promotions\Enums\PromotionStackingMode;
use App\Domain\Promotions\Enums\PromotionTriggerType;
use App\Domain\Promotions\Models\DiscountCode;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\PromotionAction;
use App\Domain\Promotions\Models\PromotionRule;
use App\Domain\Promotions\Models\PromotionUsage;
use App\Domain\Promotions\Support\PromotionContext;
use App\Domain\Promotions\Support\PromotionEngine;
use App\Domain\Promotions\Support\PromotionLine;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

/**
 * Exercises RuleEngine/ActionEngine/PromotionEngine directly against
 * hand-built PromotionContext values — no Cart/Checkout HTTP round trip
 * needed, since none of these rule/action types query anything beyond
 * what's already on the context (except UsageLimit, which reads
 * PromotionUsage). See CheckoutDiscountCodeTest for the storefront-level
 * integration coverage.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

function line(string $variantId = 'variant-1', int $quantity = 1, int $unitPrice = 1000, array $collectionIds = [], array $categoryIds = []): PromotionLine
{
    return new PromotionLine('product-1', $variantId, $quantity, $unitPrice, $collectionIds, $categoryIds);
}

function promotionContext(array $lines, int $shippingAmount = 0, ?DiscountCode $code = null, array $overrides = []): PromotionContext
{
    $itemsSubtotal = array_sum(array_map(fn (PromotionLine $l) => $l->lineTotal(), $lines));

    return new PromotionContext(
        lines: $lines,
        itemsSubtotal: $overrides['itemsSubtotal'] ?? $itemsSubtotal,
        shippingAmount: $shippingAmount,
        currency: $overrides['currency'] ?? 'RUB',
        countryCode: $overrides['countryCode'] ?? null,
        customerId: $overrides['customerId'] ?? null,
        customerEmail: $overrides['customerEmail'] ?? null,
        appliedDiscountCode: $code,
        now: $overrides['now'] ?? now(),
    );
}

it('applies an automatic percentage-off promotion once the minimum subtotal rule is met', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $promotion = Promotion::factory()->create(['name' => '10% off 5000+']);
        PromotionRule::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionRuleType::MinSubtotal->value, 'parameters' => ['amount' => 5000]]);
        PromotionAction::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionActionType::PercentageOff->value, 'parameters' => ['percent' => 10]]);

        $result = app(PromotionEngine::class)->handle(promotionContext([line(quantity: 6, unitPrice: 1000)]));

        expect($result->discountAmount)->toBe(600)
            ->and($result->applied)->toHaveCount(1)
            ->and($result->applied->first()->target)->toBe(Target::Order);
    });
});

it('does not apply the promotion when the minimum subtotal rule fails', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $promotion = Promotion::factory()->create();
        PromotionRule::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionRuleType::MinSubtotal->value, 'parameters' => ['amount' => 5000]]);
        PromotionAction::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionActionType::PercentageOff->value, 'parameters' => ['percent' => 10]]);

        $result = app(PromotionEngine::class)->handle(promotionContext([line(quantity: 2, unitPrice: 1000)]));

        expect($result->discountAmount)->toBe(0)
            ->and($result->applied)->toBeEmpty();
    });
});

it('applies free shipping', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $promotion = Promotion::factory()->create();
        PromotionAction::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionActionType::FreeShipping->value, 'parameters' => []]);

        $result = app(PromotionEngine::class)->handle(promotionContext([line()], shippingAmount: 500));

        expect($result->discountAmount)->toBe(500)
            ->and($result->applied->first()->target)->toBe(Target::Shipping);
    });
});

it('applies a Buy X Get Y free product', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $promotion = Promotion::factory()->create();
        // "Buy X": cart must contain the trigger product.
        PromotionRule::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionRuleType::Product->value, 'parameters' => ['product_ids' => ['variant-x']]]);
        // "Get Y": one unit of a different variant, free.
        PromotionAction::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionActionType::FreeProduct->value, 'parameters' => ['product_variant_id' => 'variant-y', 'quantity' => 1]]);

        $lines = [
            line('variant-x', quantity: 2, unitPrice: 1000),
            line('variant-y', quantity: 1, unitPrice: 700),
        ];

        $result = app(PromotionEngine::class)->handle(promotionContext($lines));

        expect($result->discountAmount)->toBe(700)
            ->and($result->applied->first()->target)->toBe(Target::LineItem)
            ->and($result->applied->first()->productVariantId)->toBe('variant-y');
    });
});

it('sums two stackable promotions applied in ascending priority order', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $first = Promotion::factory()->create(['stacking_mode' => PromotionStackingMode::Stackable, 'priority' => 0]);
        PromotionAction::query()->create(['promotion_id' => $first->id, 'type' => PromotionActionType::OrderDiscount->value, 'parameters' => ['amount' => 100]]);

        $second = Promotion::factory()->create(['stacking_mode' => PromotionStackingMode::Stackable, 'priority' => 1]);
        PromotionAction::query()->create(['promotion_id' => $second->id, 'type' => PromotionActionType::OrderDiscount->value, 'parameters' => ['amount' => 50]]);

        $result = app(PromotionEngine::class)->handle(promotionContext([line(quantity: 5, unitPrice: 1000)]));

        expect($result->discountAmount)->toBe(150)
            ->and($result->applied)->toHaveCount(2);
    });
});

it('lets a single eligible exclusive promotion win over stackable ones', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $exclusive = Promotion::factory()->create(['stacking_mode' => PromotionStackingMode::Exclusive, 'priority' => 0, 'name' => 'Exclusive 20%']);
        PromotionAction::query()->create(['promotion_id' => $exclusive->id, 'type' => PromotionActionType::PercentageOff->value, 'parameters' => ['percent' => 20]]);

        $stackable = Promotion::factory()->create(['stacking_mode' => PromotionStackingMode::Stackable, 'priority' => 1, 'name' => 'Stackable 50 off']);
        PromotionAction::query()->create(['promotion_id' => $stackable->id, 'type' => PromotionActionType::OrderDiscount->value, 'parameters' => ['amount' => 50]]);

        $result = app(PromotionEngine::class)->handle(promotionContext([line(quantity: 1, unitPrice: 1000)]));

        expect($result->discountAmount)->toBe(200)
            ->and($result->applied)->toHaveCount(1)
            ->and($result->applied->first()->promotion->id)->toBe($exclusive->id);
    });
});

it('picks the lowest-priority-number exclusive when several exclusives are eligible', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $low = Promotion::factory()->create(['stacking_mode' => PromotionStackingMode::Exclusive, 'priority' => 0]);
        PromotionAction::query()->create(['promotion_id' => $low->id, 'type' => PromotionActionType::OrderDiscount->value, 'parameters' => ['amount' => 10]]);

        $high = Promotion::factory()->create(['stacking_mode' => PromotionStackingMode::Exclusive, 'priority' => 5]);
        PromotionAction::query()->create(['promotion_id' => $high->id, 'type' => PromotionActionType::OrderDiscount->value, 'parameters' => ['amount' => 900]]);

        $result = app(PromotionEngine::class)->handle(promotionContext([line(quantity: 1, unitPrice: 1000)]));

        expect($result->applied)->toHaveCount(1)
            ->and($result->applied->first()->promotion->id)->toBe($low->id)
            ->and($result->discountAmount)->toBe(10);
    });
});

it('enforces a promotion-level usage_limit rule independent of any discount code', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $promotion = Promotion::factory()->create();
        PromotionRule::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionRuleType::UsageLimit->value, 'parameters' => ['max_uses' => 1]]);
        PromotionAction::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionActionType::OrderDiscount->value, 'parameters' => ['amount' => 100]]);

        PromotionUsage::query()->create([
            'promotion_id' => $promotion->id,
            'order_id' => Order::factory()->create()->id,
            'amount' => 100,
        ]);

        $result = app(PromotionEngine::class)->handle(promotionContext([line(quantity: 1, unitPrice: 1000)]));

        expect($result->discountAmount)->toBe(0);
    });
});

it('applies a code-triggered promotion only when the applied code matches and its own rules pass', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $promotion = Promotion::factory()->create(['trigger_type' => PromotionTriggerType::Code]);
        PromotionRule::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionRuleType::MinSubtotal->value, 'parameters' => ['amount' => 1000]]);
        PromotionAction::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionActionType::OrderDiscount->value, 'parameters' => ['amount' => 200]]);

        $code = DiscountCode::query()->create(['promotion_id' => $promotion->id, 'code' => 'save20', 'status' => DiscountCodeStatus::Active->value]);

        // No code applied at all -> nothing.
        $withoutCode = app(PromotionEngine::class)->handle(promotionContext([line(quantity: 1, unitPrice: 1000)]));
        expect($withoutCode->discountAmount)->toBe(0);

        // Code applied, case-insensitively looked up, but cart doesn't
        // earn it (min_subtotal fails) -> nothing.
        $found = DiscountCode::findByCode('SAVE20');
        expect($found?->id)->toBe($code->id);

        $tooSmall = app(PromotionEngine::class)->handle(promotionContext([line(quantity: 1, unitPrice: 500)], code: $found));
        expect($tooSmall->discountAmount)->toBe(0);

        // Code applied and cart qualifies -> discount applied, tagged
        // with the code.
        $qualifies = app(PromotionEngine::class)->handle(promotionContext([line(quantity: 1, unitPrice: 1000)], code: $found));
        expect($qualifies->discountAmount)->toBe(200)
            ->and($qualifies->applied->first()->discountCode?->id)->toBe($code->id);
    });
});

it('excludes an exhausted or expired discount code from eligibility', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $promotion = Promotion::factory()->create(['trigger_type' => PromotionTriggerType::Code]);
        PromotionAction::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionActionType::OrderDiscount->value, 'parameters' => ['amount' => 200]]);

        $exhausted = DiscountCode::query()->create([
            'promotion_id' => $promotion->id, 'code' => 'USED', 'usage_limit' => 1, 'usage_count' => 1,
            'status' => DiscountCodeStatus::Active->value,
        ]);
        $expired = DiscountCode::query()->create([
            'promotion_id' => $promotion->id, 'code' => 'OLD', 'expires_at' => now()->subDay(),
            'status' => DiscountCodeStatus::Active->value,
        ]);

        expect(app(PromotionEngine::class)->handle(promotionContext([line()], code: $exhausted))->discountAmount)->toBe(0)
            ->and(app(PromotionEngine::class)->handle(promotionContext([line()], code: $expired))->discountAmount)->toBe(0);
    });
});
