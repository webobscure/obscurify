<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductOption;
use App\Domain\Catalog\Models\ProductOptionValue;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

function productForOptions(array $overrides = []): array
{
    $user = User::factory()->create();
    $store = createStoreForUser($user);
    $product = app(TenantContext::class)->scope($store, fn () => Product::factory()->create($overrides));

    return [$user, $store, $product];
}

beforeEach(function () {
    [$this->user, $this->store, $this->product] = productForOptions();
});

it('creates a product option with values', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$this->product->id}/options", ['name' => 'Color'], tenantHeader($this->store))
        ->assertCreated()
        ->assertJsonPath('data.name', 'Color');

    $optionId = $response->json('data.id');

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$this->product->id}/options/{$optionId}/values", ['value' => 'Black'], tenantHeader($this->store))
        ->assertCreated()
        ->assertJsonPath('data.value', 'Black');

    app(TenantContext::class)->scope($this->store, function () {
        expect(ProductOption::query()->count())->toBe(1);
        expect(ProductOptionValue::query()->count())->toBe(1);
    });
});

it('rejects a duplicate option name on the same product', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$this->product->id}/options", ['name' => 'Color'], tenantHeader($this->store))
        ->assertCreated();

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$this->product->id}/options", ['name' => 'Color'], tenantHeader($this->store))
        ->assertStatus(422);
});

it('allows the same option name on a different product in the same store', function () {
    $otherProduct = app(TenantContext::class)->scope($this->store, fn () => Product::factory()->create());

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$this->product->id}/options", ['name' => 'Color'], tenantHeader($this->store))
        ->assertCreated();

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$otherProduct->id}/options", ['name' => 'Color'], tenantHeader($this->store))
        ->assertCreated();
});

it('rejects a duplicate value on the same option', function () {
    $option = app(TenantContext::class)->scope($this->store, fn () => ProductOption::factory()->create(['product_id' => $this->product->id, 'name' => 'Color']));

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$this->product->id}/options/{$option->id}/values", ['value' => 'Black'], tenantHeader($this->store))
        ->assertCreated();

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$this->product->id}/options/{$option->id}/values", ['value' => 'Black'], tenantHeader($this->store))
        ->assertStatus(422);
});

it('refuses to delete an option value that is used by an existing variant', function () {
    $option = app(TenantContext::class)->scope($this->store, fn () => ProductOption::factory()->create(['product_id' => $this->product->id]));
    $value = app(TenantContext::class)->scope($this->store, fn () => ProductOptionValue::factory()->create(['product_option_id' => $option->id, 'value' => 'Black']));

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$this->product->id}/variants", [
            'price_amount' => 1000,
            'option_value_ids' => [$value->id],
        ], tenantHeader($this->store))
        ->assertCreated();

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/products/{$this->product->id}/options/{$option->id}/values/{$value->id}", [], tenantHeader($this->store))
        ->assertStatus(422);

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/products/{$this->product->id}/options/{$option->id}", [], tenantHeader($this->store))
        ->assertStatus(422);
});

it('deletes an unused option and its values', function () {
    $option = app(TenantContext::class)->scope($this->store, fn () => ProductOption::factory()->create(['product_id' => $this->product->id]));
    app(TenantContext::class)->scope($this->store, fn () => ProductOptionValue::factory()->create(['product_option_id' => $option->id]));

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/products/{$this->product->id}/options/{$option->id}", [], tenantHeader($this->store))
        ->assertNoContent();

    app(TenantContext::class)->scope($this->store, function () use ($option) {
        expect(ProductOption::query()->find($option->id))->toBeNull();
    });
    expect(ProductOptionValue::withoutGlobalScopes()->where('product_option_id', $option->id)->count())->toBe(0);
});
