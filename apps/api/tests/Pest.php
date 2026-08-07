<?php

use App\Domain\Stores\Enums\StoreUserRole;
use App\Domain\Stores\Enums\StoreUserStatus;
use App\Domain\Stores\Models\Store;
use App\Domain\Stores\Models\StoreUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Creates a Store owned by the given user, with an active owner membership
 * already attached — the same invariant CreateStore enforces atomically.
 */
function createStoreForUser(User $user, array $overrides = [], StoreUserRole $role = StoreUserRole::Owner): Store
{
    $store = Store::factory()->create([
        'owner_id' => $user->id,
        ...$overrides,
    ]);

    $membership = new StoreUser([
        'role' => $role,
        'status' => StoreUserStatus::Active,
    ]);
    $membership->store_id = $store->id;
    $membership->user_id = $user->id;
    $membership->save();

    return $store;
}

/**
 * Header used to select the active tenant on merchant-admin requests.
 */
function tenantHeader(Store $store): array
{
    return ['X-Store-Id' => $store->id];
}
