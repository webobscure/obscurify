<?php

use App\Domain\Domains\Models\Domain;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

function domainForStore(Store $store, string $host, array $overrides = []): Domain
{
    return app(TenantContext::class)->scope(
        $store,
        fn () => Domain::query()->create(['domain' => $host, 'type' => 'primary', 'is_primary' => true, ...$overrides]),
    );
}

/**
 * Builds an absolute URL with the given host so Symfony's Request::create()
 * derives HTTP_HOST from the URI itself — passing a bare 'Host' header
 * does NOT work here: Laravel's test client always resolves relative URIs
 * against an absolute base URL first, and Symfony\Component\HttpFoundation\Request::create()
 * unconditionally overwrites HTTP_HOST from any host present in the URI
 * it's given (see Request::create(), the `isset($components['host'])`
 * branch) — so the only reliable way to control the effective request
 * host in a test is to make the URI itself carry it.
 */
function storefrontUrl(string $host, string $path): string
{
    return "http://{$host}{$path}";
}

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
