<?php

use App\Domain\Catalog\Models\Category;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);
    $this->categoryB = app(TenantContext::class)->scope($this->storeB, fn () => Category::factory()->create());
});

it('builds a category hierarchy', function () {
    $electronics = $this->actingAs($this->userA, 'sanctum')
        ->postJson('/api/v1/categories', ['title' => 'Electronics'], tenantHeader($this->storeA))
        ->assertCreated()
        ->json('data.id');

    $tv = $this->actingAs($this->userA, 'sanctum')
        ->postJson('/api/v1/categories', ['title' => 'TV', 'parent_id' => $electronics], tenantHeader($this->storeA))
        ->assertCreated()
        ->assertJsonPath('data.parent_id', $electronics)
        ->json('data.id');

    $this->actingAs($this->userA, 'sanctum')
        ->getJson("/api/v1/categories/{$electronics}", tenantHeader($this->storeA))
        ->assertOk()
        ->assertJsonPath('data.children.0.id', $tv);
});

it('rejects a category becoming its own parent', function () {
    $category = app(TenantContext::class)->scope($this->storeA, fn () => Category::factory()->create());

    $this->actingAs($this->userA, 'sanctum')
        ->patchJson("/api/v1/categories/{$category->id}", ['parent_id' => $category->id], tenantHeader($this->storeA))
        ->assertStatus(422);
});

it('rejects a parent change that would create a cycle', function () {
    [$parent, $child] = app(TenantContext::class)->scope($this->storeA, function () {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        return [$parent, $child];
    });

    $this->actingAs($this->userA, 'sanctum')
        ->patchJson("/api/v1/categories/{$parent->id}", ['parent_id' => $child->id], tenantHeader($this->storeA))
        ->assertStatus(422);
});

it('rejects a Store B category id as a parent', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->postJson('/api/v1/categories', ['title' => 'Electronics', 'parent_id' => $this->categoryB->id], tenantHeader($this->storeA))
        ->assertStatus(422);
});

it('does not let Store A read or modify a Store B category', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->getJson("/api/v1/categories/{$this->categoryB->id}", tenantHeader($this->storeA))
        ->assertNotFound();

    $this->actingAs($this->userA, 'sanctum')
        ->deleteJson("/api/v1/categories/{$this->categoryB->id}", [], tenantHeader($this->storeA))
        ->assertNotFound();

    expect(Category::withoutGlobalScopes()->find($this->categoryB->id))->not->toBeNull();
});
