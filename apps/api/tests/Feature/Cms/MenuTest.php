<?php

use App\Domain\Cms\Models\Menu;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);

    $this->menuB = app(TenantContext::class)->scope($this->storeB, fn () => Menu::query()->create(['name' => 'Store B Menu', 'handle' => 'main']));
});

it('creates a menu and adds nested items to it', function () {
    $menu = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/menus', [
        'name' => 'Main Menu', 'handle' => 'main-menu',
    ], tenantHeader($this->storeA))->assertCreated();

    $parent = $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/menus/{$menu->json('data.id')}/items",
        ['label' => 'Shop', 'target_type' => 'url', 'url' => '/shop'],
        tenantHeader($this->storeA),
    )->assertCreated();

    $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/menus/{$menu->json('data.id')}/items",
        ['label' => 'T-Shirts', 'target_type' => 'url', 'url' => '/shop/t-shirts', 'parent_id' => $parent->json('data.id')],
        tenantHeader($this->storeA),
    )->assertCreated();

    $show = $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/menus/{$menu->json('data.id')}", tenantHeader($this->storeA))->assertOk();

    expect($show->json('data.items'))->toHaveCount(1)
        ->and($show->json('data.items.0.label'))->toBe('Shop')
        ->and($show->json('data.items.0.children'))->toHaveCount(1)
        ->and($show->json('data.items.0.children.0.label'))->toBe('T-Shirts');
});

it('requires a url when target_type is url, and rejects one otherwise', function () {
    $menu = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/menus', [
        'name' => 'Main Menu', 'handle' => 'main-menu',
    ], tenantHeader($this->storeA))->assertCreated();

    $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/menus/{$menu->json('data.id')}/items",
        ['label' => 'Broken', 'target_type' => 'url'],
        tenantHeader($this->storeA),
    )->assertStatus(422);

    $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/menus/{$menu->json('data.id')}/items",
        ['label' => 'Broken', 'target_type' => 'page'],
        tenantHeader($this->storeA),
    )->assertStatus(422);
});

it('resolves a menu on the storefront, including a nested tree with hrefs', function () {
    $menu = app(TenantContext::class)->scope($this->storeA, function () {
        $menu = Menu::query()->create(['name' => 'Main Menu', 'handle' => 'main-menu']);
        $parent = $menu->items()->create(['label' => 'Shop', 'target_type' => 'url', 'url' => '/shop', 'position' => 0]);
        $menu->items()->create(['label' => 'Sale', 'target_type' => 'url', 'url' => '/sale', 'position' => 1, 'parent_id' => $parent->id]);

        return $menu;
    });

    domainForStore($this->storeA, 'cms-menu-test.localhost');

    $response = $this->getJson(storefrontUrl('cms-menu-test.localhost', "/api/v1/storefront/menus/{$menu->handle}"))->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.label'))->toBe('Shop')
        ->and($response->json('data.0.href'))->toBe('/shop')
        ->and($response->json('data.0.children.0.label'))->toBe('Sale')
        ->and($response->json('data.0.children.0.href'))->toBe('/sale');
});

it('never lets Store A read, edit, or delete a Store B menu', function () {
    $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/menus/{$this->menuB->id}", tenantHeader($this->storeA))->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->patchJson("/api/v1/menus/{$this->menuB->id}", ['name' => 'Hijacked'], tenantHeader($this->storeA))->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->deleteJson("/api/v1/menus/{$this->menuB->id}", [], tenantHeader($this->storeA))->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/menus/{$this->menuB->id}/items", ['label' => 'x', 'target_type' => 'url', 'url' => '/x'], tenantHeader($this->storeA))->assertNotFound();
});
