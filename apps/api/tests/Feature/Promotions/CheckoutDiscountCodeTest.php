<?php

use App\Domain\Promotions\Enums\DiscountCodeStatus;
use App\Domain\Promotions\Enums\PromotionActionType;
use App\Domain\Promotions\Enums\PromotionRuleType;
use App\Domain\Promotions\Enums\PromotionTriggerType;
use App\Domain\Promotions\Models\DiscountApplication;
use App\Domain\Promotions\Models\DiscountCode;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\PromotionAction;
use App\Domain\Promotions\Models\PromotionRule;
use App\Domain\Promotions\Models\PromotionUsage;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->withCredentials();

    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    domainForStore($this->storeA, 'store-a.localhost');

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);
    domainForStore($this->storeB, 'store-b.localhost');

    // price_amount 1000 per unit (see productWithStock).
    [$this->productA, $this->variantA] = productWithStock($this->storeA, 10);
});

function addToCartAndOpenCheckout(string $host, string $variantId, int $quantity): string
{
    $add = test()->postJson(storefrontUrl($host, '/api/v1/storefront/cart/items'), [
        'variant_id' => $variantId,
        'quantity' => $quantity,
    ])->assertOk();

    $token = $add->headers->getCookies()[0]->getValue();
    test()->withUnencryptedCookie('storefront_cart_token', $token);
    test()->postJson(storefrontUrl($host, '/api/v1/storefront/checkout'))->assertOk();

    test()->withUnencryptedCookie('storefront_cart_token', $token);
    test()->patchJson(storefrontUrl($host, '/api/v1/storefront/checkout'), [
        'email' => 'buyer@example.com',
        'shipping_address' => [
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'US', 'city' => 'Testville',
        ],
    ])->assertOk();

    return $token;
}

it('applies an automatic promotion as soon as the cart earns it, with no code involved', function () {
    app(TenantContext::class)->scope($this->storeA, function () {
        $promotion = Promotion::factory()->create(['trigger_type' => PromotionTriggerType::Automatic]);
        PromotionRule::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionRuleType::MinSubtotal->value, 'parameters' => ['amount' => 2000]]);
        PromotionAction::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionActionType::PercentageOff->value, 'parameters' => ['percent' => 10]]);
    });

    $token = addToCartAndOpenCheckout('store-a.localhost', $this->variantA->id, 3);

    // Re-fetch the checkout state via update (no-op payload) to read totals
    // — OpenCheckout already recalculated automatic promotions above.
    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $state = $this->patchJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout'), ['email' => 'buyer@example.com'])->assertOk();

    expect($state->json('data.discount_amount'))->toBe(300)
        ->and($state->json('data.total_amount'))->toBe(2700);
});

it('applies, then removes, a discount code on checkout, recalculating totals both times', function () {
    app(TenantContext::class)->scope($this->storeA, function () {
        $promotion = Promotion::factory()->create(['trigger_type' => PromotionTriggerType::Code]);
        PromotionAction::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionActionType::OrderDiscount->value, 'parameters' => ['amount' => 500]]);
        DiscountCode::query()->create(['promotion_id' => $promotion->id, 'code' => 'save5', 'status' => DiscountCodeStatus::Active->value]);
    });

    $token = addToCartAndOpenCheckout('store-a.localhost', $this->variantA->id, 3);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    // Case-insensitive lookup (spec section 6).
    $applied = $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/discount-code'), ['code' => 'SAVE5'])->assertOk();

    expect($applied->json('data.discount_code'))->toBe('SAVE5')
        ->and($applied->json('data.discount_amount'))->toBe(500)
        ->and($applied->json('data.total_amount'))->toBe(2500);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $removed = $this->deleteJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/discount-code'))->assertOk();

    expect($removed->json('data.discount_code'))->toBeNull()
        ->and($removed->json('data.discount_amount'))->toBe(0)
        ->and($removed->json('data.total_amount'))->toBe(3000);
});

it('rejects an unknown, inactive, or unqualifying discount code without applying anything', function () {
    app(TenantContext::class)->scope($this->storeA, function () {
        $promotion = Promotion::factory()->create(['trigger_type' => PromotionTriggerType::Code]);
        PromotionRule::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionRuleType::MinSubtotal->value, 'parameters' => ['amount' => 100000]]);
        PromotionAction::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionActionType::OrderDiscount->value, 'parameters' => ['amount' => 500]]);
        DiscountCode::query()->create(['promotion_id' => $promotion->id, 'code' => 'TOOBIG', 'status' => DiscountCodeStatus::Active->value]);
        DiscountCode::query()->create(['promotion_id' => $promotion->id, 'code' => 'OFFCODE', 'status' => DiscountCodeStatus::Inactive->value]);
    });

    $token = addToCartAndOpenCheckout('store-a.localhost', $this->variantA->id, 1);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/discount-code'), ['code' => 'NOPE'])->assertStatus(422);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/discount-code'), ['code' => 'OFFCODE'])->assertStatus(422);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/discount-code'), ['code' => 'TOOBIG'])->assertStatus(422);
});

it('never lets Store B redeem a Store A discount code', function () {
    app(TenantContext::class)->scope($this->storeA, function () {
        $promotion = Promotion::factory()->create(['trigger_type' => PromotionTriggerType::Code]);
        PromotionAction::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionActionType::OrderDiscount->value, 'parameters' => ['amount' => 500]]);
        DiscountCode::query()->create(['promotion_id' => $promotion->id, 'code' => 'CROSSTENANT', 'status' => DiscountCodeStatus::Active->value]);
    });

    [, $variantB] = productWithStock($this->storeB, 10);
    $token = addToCartAndOpenCheckout('store-b.localhost', $variantB->id, 1);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->postJson(storefrontUrl('store-b.localhost', '/api/v1/storefront/checkout/discount-code'), ['code' => 'CROSSTENANT'])
        ->assertStatus(422);
});

it('snapshots the applied discount code onto the Order and records usage on completion', function () {
    $promotion = app(TenantContext::class)->scope($this->storeA, function () {
        $promotion = Promotion::factory()->create(['trigger_type' => PromotionTriggerType::Code, 'name' => 'Welcome discount']);
        PromotionAction::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionActionType::OrderDiscount->value, 'parameters' => ['amount' => 400]]);
        DiscountCode::query()->create(['promotion_id' => $promotion->id, 'code' => 'WELCOME', 'usage_limit' => 5, 'status' => DiscountCodeStatus::Active->value]);

        return $promotion;
    });

    $token = addToCartAndOpenCheckout('store-a.localhost', $this->variantA->id, 3);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/discount-code'), ['code' => 'WELCOME'])->assertOk();

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $complete = $this->postJson(
        storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'promo-snapshot-key'],
    )->assertCreated();

    expect($complete->json('data.discount_amount'))->toBe(400)
        ->and($complete->json('data.total_amount'))->toBe(2600)
        ->and($complete->json('data.discount_applications'))->toHaveCount(1)
        ->and($complete->json('data.discount_applications.0.promotion_name'))->toBe('Welcome discount')
        ->and($complete->json('data.discount_applications.0.code'))->toBe('WELCOME')
        ->and($complete->json('data.discount_applications.0.amount'))->toBe(400);

    app(TenantContext::class)->scope($this->storeA, function () use ($promotion, $complete) {
        expect(DiscountApplication::query()->count())->toBe(1)
            ->and(DiscountApplication::query()->firstOrFail()->order_id)->toBe($complete->json('data.id'));

        $usage = PromotionUsage::query()->firstOrFail();
        expect($usage->promotion_id)->toBe($promotion->id)
            ->and($usage->amount)->toBe(400);

        expect(DiscountCode::query()->firstOrFail()->usage_count)->toBe(1);
    });
});
