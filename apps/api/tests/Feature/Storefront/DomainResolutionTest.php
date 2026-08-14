<?php

use App\Models\User;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    domainForStore($this->storeA, 'store-a.localhost');

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);
    domainForStore($this->storeB, 'store-b.localhost');
});

it('resolves the active store from a matching domain', function () {
    $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/store'))
        ->assertOk()
        ->assertJsonPath('data.name', $this->storeA->name);

    $this->getJson(storefrontUrl('store-b.localhost', '/api/v1/storefront/store'))
        ->assertOk()
        ->assertJsonPath('data.name', $this->storeB->name);
});

it('returns 404 for an unknown domain', function () {
    $this->getJson(storefrontUrl('unknown.localhost', '/api/v1/storefront/store'))
        ->assertNotFound();
});

it('normalizes host casing and port before matching', function () {
    $this->getJson(storefrontUrl('Store-A.LOCALHOST', '/api/v1/storefront/store'))
        ->assertOk()
        ->assertJsonPath('data.name', $this->storeA->name);

    $this->getJson(storefrontUrl('store-a.localhost:8000', '/api/v1/storefront/store'))
        ->assertOk()
        ->assertJsonPath('data.name', $this->storeA->name);
});

it('does not merge www and non-www without an explicit Domain row', function () {
    $this->getJson(storefrontUrl('www.store-a.localhost', '/api/v1/storefront/store'))
        ->assertNotFound();
});
