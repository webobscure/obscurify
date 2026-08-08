<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductOption;
use App\Domain\Catalog\Models\ProductOptionValue;
use App\Domain\Catalog\Models\ProductVariant;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    $this->productA = app(TenantContext::class)->scope($this->storeA, fn () => Product::factory()->create());

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);
    $this->productB = app(TenantContext::class)->scope($this->storeB, fn () => Product::factory()->create());

    [$this->optionB, $this->valueB] = app(TenantContext::class)->scope($this->storeB, function () {
        $option = ProductOption::factory()->create(['product_id' => $this->productB->id]);
        $value = ProductOptionValue::factory()->create(['product_option_id' => $option->id]);

        return [$option, $value];
    });

    $this->variantB = app(TenantContext::class)->scope($this->storeB, fn () => ProductVariant::factory()->create(['product_id' => $this->productB->id]));
});

it('does not let Store A resolve a Store B option through a Store A product', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->patchJson(
            "/api/v1/products/{$this->productA->id}/options/{$this->optionB->id}",
            ['name' => 'Hacked'],
            tenantHeader($this->storeA),
        )->assertNotFound();
});

it('does not let Store A resolve a Store B option value through a Store A product/option', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->deleteJson(
            "/api/v1/products/{$this->productA->id}/options/{$this->optionB->id}/values/{$this->valueB->id}",
            [],
            tenantHeader($this->storeA),
        )->assertNotFound();
});

it('cannot create a Store A variant on a Store B product', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->postJson(
            "/api/v1/products/{$this->productB->id}/variants",
            ['price_amount' => 1000],
            tenantHeader($this->storeA),
        )->assertNotFound();
});

it('does not let Store A resolve a Store B variant through a Store A product', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->patchJson(
            "/api/v1/products/{$this->productA->id}/variants/{$this->variantB->id}",
            ['price_amount' => 1],
            tenantHeader($this->storeA),
        )->assertNotFound();

    expect($this->variantB->fresh()->price_amount)->not->toBe(1);
});

it('rejects a Store B option value when creating a Store A variant', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->postJson(
            "/api/v1/products/{$this->productA->id}/variants",
            ['price_amount' => 1000, 'option_value_ids' => [$this->valueB->id]],
            tenantHeader($this->storeA),
        )->assertStatus(422);
});
