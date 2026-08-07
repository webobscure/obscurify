<?php

use App\Domain\Stores\Enums\StoreUserRole;
use App\Models\User;
use Illuminate\Database\QueryException;

it('creates a store atomically and makes the creator the owner', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/stores', [
        'name' => 'Alice Shop',
        'slug' => 'alice-shop',
    ]);

    $response->assertCreated()->assertJsonPath('data.name', 'Alice Shop');

    $storeId = $response->json('data.id');

    $this->assertDatabaseHas('stores', ['id' => $storeId, 'owner_id' => $user->id]);
    $this->assertDatabaseHas('store_users', [
        'store_id' => $storeId,
        'user_id' => $user->id,
        'role' => StoreUserRole::Owner->value,
    ]);
});

it('lets a user activate a store they belong to', function () {
    $user = User::factory()->create();
    $store = createStoreForUser($user);

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/stores/{$store->id}/activate");

    $response->assertOk()->assertJsonPath('data.id', $store->id);
});

it('does not let a user activate a store they do not belong to', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $storeB = createStoreForUser($userB);

    $response = $this->actingAs($userA, 'sanctum')->postJson("/api/v1/stores/{$storeB->id}/activate");

    $response->assertForbidden();
});

it('lets a user who belongs to two stores activate either of them', function () {
    $owner = User::factory()->create();
    $storeA = createStoreForUser($owner);
    $storeB = createStoreForUser($owner);

    $member = User::factory()->create();
    $storeA->users()->attach($member, [
        'role' => StoreUserRole::Manager->value,
        'status' => 'active',
    ]);
    $storeB->users()->attach($member, [
        'role' => StoreUserRole::Manager->value,
        'status' => 'active',
    ]);

    $this->actingAs($member, 'sanctum')
        ->postJson("/api/v1/stores/{$storeA->id}/activate")
        ->assertOk();

    $this->actingAs($member, 'sanctum')
        ->postJson("/api/v1/stores/{$storeB->id}/activate")
        ->assertOk();
});

it('does not allow duplicate membership for the same user and store', function () {
    $user = User::factory()->create();
    $store = createStoreForUser($user);

    expect(fn () => $store->users()->attach($user, ['role' => 'manager', 'status' => 'active']))
        ->toThrow(QueryException::class);
});

it('lists only stores the authenticated user belongs to', function () {
    $user = User::factory()->create();
    $ownStore = createStoreForUser($user);

    $otherOwner = User::factory()->create();
    createStoreForUser($otherOwner);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/stores');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownStore->id);
});
