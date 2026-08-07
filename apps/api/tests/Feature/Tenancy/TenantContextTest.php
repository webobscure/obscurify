<?php

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Exceptions\TenantContextMissingException;
use App\Shared\Tenancy\TenantContext;

it('fails closed when no store has been set', function () {
    $tenant = app(TenantContext::class);

    expect($tenant->has())->toBeFalse();
    expect(fn () => $tenant->store())->toThrow(TenantContextMissingException::class);
});

it('scopes a callback to a store and restores the previous context afterwards', function () {
    $tenant = app(TenantContext::class);
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();

    $tenant->set($storeA);

    $tenant->scope($storeB, function () use ($tenant, $storeB) {
        expect($tenant->storeId())->toBe($storeB->id);
    });

    expect($tenant->storeId())->toBe($storeA->id);
});

it('clears the active store', function () {
    $tenant = app(TenantContext::class);
    $tenant->set(Store::factory()->create());

    $tenant->clear();

    expect($tenant->has())->toBeFalse();
});
