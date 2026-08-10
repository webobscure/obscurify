<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Promotions\Enums\PromotionActionType;
use App\Domain\Promotions\Models\DiscountApplication;
use App\Domain\Promotions\Models\DiscountCode;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\PromotionAction;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);

    $this->promotionB = app(TenantContext::class)->scope($this->storeB, fn () => Promotion::factory()->create(['name' => 'Store B promo']));
});

it('creates a promotion with rules and actions', function () {
    $response = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/promotions', [
        'name' => 'Spring sale',
        'trigger_type' => 'automatic',
        'stacking_mode' => 'stackable',
        'priority' => 1,
        'rules' => [
            ['type' => 'min_subtotal', 'parameters' => ['amount' => 5000]],
        ],
        'actions' => [
            ['type' => 'percentage_off', 'parameters' => ['percent' => 15]],
        ],
    ], tenantHeader($this->storeA))->assertCreated();

    expect($response->json('data.name'))->toBe('Spring sale')
        ->and($response->json('data.rules'))->toHaveCount(1)
        ->and($response->json('data.rules.0.type'))->toBe('min_subtotal')
        ->and($response->json('data.actions.0.type'))->toBe('percentage_off');
});

it('updates a promotion, replacing its rules/actions in full', function () {
    $created = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/promotions', [
        'name' => 'Original',
        'actions' => [['type' => 'free_shipping', 'parameters' => []]],
    ], tenantHeader($this->storeA))->assertCreated();

    $updated = $this->actingAs($this->userA, 'sanctum')->patchJson("/api/v1/promotions/{$created->json('data.id')}", [
        'name' => 'Renamed',
        'actions' => [
            ['type' => 'order_discount', 'parameters' => ['amount' => 300]],
            ['type' => 'free_shipping', 'parameters' => []],
        ],
    ], tenantHeader($this->storeA))->assertOk();

    expect($updated->json('data.name'))->toBe('Renamed')
        ->and($updated->json('data.actions'))->toHaveCount(2);
});

it('lists and shows promotions scoped to the active store', function () {
    $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/promotions', ['name' => 'Store A promo'], tenantHeader($this->storeA))->assertCreated();

    $index = $this->actingAs($this->userA, 'sanctum')->getJson('/api/v1/promotions', tenantHeader($this->storeA))->assertOk();
    expect(collect($index->json('data'))->pluck('name')->all())->toBe(['Store A promo']);

    $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/promotions/{$this->promotionB->id}", tenantHeader($this->storeA))
        ->assertNotFound();
});

it('never lets Store A read, update, or see usage for Store B promotions', function () {
    $this->actingAs($this->userA, 'sanctum')->patchJson("/api/v1/promotions/{$this->promotionB->id}", [
        'name' => 'Hijacked',
    ], tenantHeader($this->storeA))->assertNotFound();

    $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/promotions/{$this->promotionB->id}/usage", tenantHeader($this->storeA))
        ->assertNotFound();
});

it('creates and updates discount codes under a promotion', function () {
    $promotion = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/promotions', [
        'name' => 'Coupon promo',
        'trigger_type' => 'code',
        'actions' => [['type' => 'order_discount', 'parameters' => ['amount' => 500]]],
    ], tenantHeader($this->storeA))->assertCreated();

    $code = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/discount-codes', [
        'promotion_id' => $promotion->json('data.id'),
        'code' => 'welcome10',
        'usage_limit' => 100,
    ], tenantHeader($this->storeA))->assertCreated();

    // Normalized to uppercase (DiscountCode::setCodeAttribute).
    expect($code->json('data.code'))->toBe('WELCOME10')
        ->and($code->json('data.usage_limit'))->toBe(100);

    $updated = $this->actingAs($this->userA, 'sanctum')->patchJson("/api/v1/discount-codes/{$code->json('data.id')}", [
        'status' => 'inactive',
    ], tenantHeader($this->storeA))->assertOk();

    expect($updated->json('data.status'))->toBe('inactive');

    $list = $this->actingAs($this->userA, 'sanctum')->getJson(
        "/api/v1/discount-codes?promotion_id={$promotion->json('data.id')}",
        tenantHeader($this->storeA),
    )->assertOk();
    expect($list->json('data'))->toHaveCount(1);
});

it('rejects creating a discount code under another store\'s promotion', function () {
    $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/discount-codes', [
        'promotion_id' => $this->promotionB->id,
        'code' => 'STEAL10',
    ], tenantHeader($this->storeA))->assertNotFound();
});

it('never lets Store A update a Store B discount code', function () {
    $codeB = app(TenantContext::class)->scope($this->storeB, fn () => DiscountCode::query()->create([
        'promotion_id' => $this->promotionB->id,
        'code' => 'STOREB',
    ]));

    $this->actingAs($this->userA, 'sanctum')->patchJson("/api/v1/discount-codes/{$codeB->id}", [
        'status' => 'inactive',
    ], tenantHeader($this->storeA))->assertNotFound();
});

it('previews promotions for a hypothetical cart without persisting anything', function () {
    [$variant] = app(TenantContext::class)->scope($this->storeA, function () {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price_amount' => 2000]);

        $promotion = Promotion::factory()->create();
        PromotionAction::query()->create(['promotion_id' => $promotion->id, 'type' => PromotionActionType::PercentageOff->value, 'parameters' => ['percent' => 10]]);

        return [$variant];
    });

    $response = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/promotions/preview', [
        'items' => [['product_variant_id' => $variant->id, 'quantity' => 3]],
    ], tenantHeader($this->storeA))->assertOk();

    expect($response->json('discount_amount'))->toBe(600)
        ->and($response->json('applied'))->toHaveCount(1);

    app(TenantContext::class)->scope($this->storeA, function () {
        expect(DiscountApplication::query()->count())->toBe(0);
    });
});
