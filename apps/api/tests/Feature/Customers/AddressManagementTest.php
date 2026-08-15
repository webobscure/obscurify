<?php

use App\Domain\Customers\Models\CustomerAddress;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Routing\Middleware\ThrottleRequests;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->host = 'e2e-address.localhost';
    domainForStore($this->store, $this->host);
    $this->withoutMiddleware(ThrottleRequests::class);

    $registered = $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/register'), [
        'email' => 'address-test@example.test',
        'password' => 'correct-password-1',
    ])->assertCreated();

    $this->accessToken = $registered->json('access_token');
});

it('creates, lists, updates, and deletes an address', function () {
    $created = $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/addresses'), [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'country_code' => 'GB',
        'city' => 'London',
        'address_line1' => '1 Analytical Engine Way',
        'is_default_shipping' => true,
    ], authHeader($this->accessToken))->assertCreated();

    $addressId = $created->json('data.id');
    expect($created->json('data.is_default_shipping'))->toBeTrue();

    $this->getJson(storefrontUrl($this->host, '/api/v1/storefront/account/addresses'), authHeader($this->accessToken))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->patchJson(storefrontUrl($this->host, "/api/v1/storefront/account/addresses/{$addressId}"), [
        'city' => 'Manchester',
    ], authHeader($this->accessToken))
        ->assertOk()
        ->assertJsonPath('data.city', 'Manchester');

    $this->deleteJson(storefrontUrl($this->host, "/api/v1/storefront/account/addresses/{$addressId}"), [], authHeader($this->accessToken))
        ->assertStatus(204);

    $this->getJson(storefrontUrl($this->host, '/api/v1/storefront/account/addresses'), authHeader($this->accessToken))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('only ever allows one default billing and one default shipping address at a time', function () {
    $first = $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/addresses'), [
        'country_code' => 'US', 'city' => 'Springfield', 'address_line1' => '1 Main St',
        'is_default_billing' => true, 'is_default_shipping' => true,
    ], authHeader($this->accessToken))->assertCreated();

    $second = $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/addresses'), [
        'country_code' => 'US', 'city' => 'Shelbyville', 'address_line1' => '2 Main St',
        'is_default_billing' => true, 'is_default_shipping' => true,
    ], authHeader($this->accessToken))->assertCreated();

    app(TenantContext::class)->scope($this->store, function () use ($first, $second) {
        $firstAddress = CustomerAddress::query()->find($first->json('data.id'));
        $secondAddress = CustomerAddress::query()->find($second->json('data.id'));

        expect($firstAddress->is_default_billing)->toBeFalse();
        expect($firstAddress->is_default_shipping)->toBeFalse();
        expect($secondAddress->is_default_billing)->toBeTrue();
        expect($secondAddress->is_default_shipping)->toBeTrue();
    });
});

it('rejects an address missing required fields', function () {
    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/addresses'), [
        'city' => 'Nowhere',
    ], authHeader($this->accessToken))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['country_code', 'address_line1']);
});

it('does not let one customer read, update, or delete another customer address in the same store', function () {
    $other = $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/register'), [
        'email' => 'other-address-owner@example.test',
        'password' => 'correct-password-1',
    ])->assertCreated();

    $otherAccessToken = $other->json('access_token');

    $address = $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/addresses'), [
        'country_code' => 'DE', 'city' => 'Berlin', 'address_line1' => 'Unter den Linden 1',
    ], authHeader($this->accessToken))->assertCreated();

    $addressId = $address->json('data.id');

    $this->patchJson(storefrontUrl($this->host, "/api/v1/storefront/account/addresses/{$addressId}"), [
        'city' => 'Hijacked',
    ], authHeader($otherAccessToken))->assertStatus(403);

    $this->deleteJson(storefrontUrl($this->host, "/api/v1/storefront/account/addresses/{$addressId}"), [], authHeader($otherAccessToken))
        ->assertStatus(403);
});
