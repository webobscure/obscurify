<?php

use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Spec section 14: Store A customer cannot access Store B customer
 * orders/addresses/sessions/tokens. Every other Customers test already
 * proves within-store ownership boundaries (customer A vs customer B in
 * the *same* store); this file is specifically the cross-store boundary.
 */
beforeEach(function () {
    $this->user = User::factory()->create();

    $this->storeA = createStoreForUser($this->user, ['slug' => 'tenant-isolation-a']);
    $this->hostA = 'e2e-isolation-a.localhost';
    domainForStore($this->storeA, $this->hostA);

    $this->storeB = createStoreForUser($this->user, ['slug' => 'tenant-isolation-b']);
    $this->hostB = 'e2e-isolation-b.localhost';
    domainForStore($this->storeB, $this->hostB);

    $this->withoutMiddleware(ThrottleRequests::class);

    $registeredA = $this->postJson(storefrontUrl($this->hostA, '/api/v1/storefront/account/register'), [
        'email' => 'isolation-customer@example.test',
        'password' => 'correct-password-1',
    ])->assertCreated();

    $this->accessTokenA = $registeredA->json('access_token');

    $this->orderB = app(TenantContext::class)->scope($this->storeB, function () {
        $customerB = Customer::factory()->create(['email' => 'store-b-customer@example.test']);

        return Order::factory()->create(['customer_id' => $customerB->id]);
    });
});

it('rejects a Store A customer token presented on Store B\'s domain', function () {
    $this->getJson(storefrontUrl($this->hostB, '/api/v1/storefront/account'), authHeader($this->accessTokenA))
        ->assertStatus(401);
});

it('never returns another stores orders in the order list, even by forged id lookup', function () {
    // The token is Store-A-scoped, so a Store-B order id 404s via Order's
    // own BelongsToTenant global scope before ownership is even checked
    // — this is what "cannot access" ultimately reduces to when domain
    // + tenant context + token are all consistent.
    $this->getJson(
        storefrontUrl($this->hostA, "/api/v1/storefront/account/orders/{$this->orderB->id}"),
        authHeader($this->accessTokenA)
    )->assertStatus(404);
});

it('never returns another stores customer in admin customer management', function () {
    $ownerToken = $this->user->createToken('test')->plainTextToken;

    $storeBCustomerId = app(TenantContext::class)->scope($this->storeB, function () {
        return Customer::query()->where('email', 'store-b-customer@example.test')->firstOrFail()->id;
    });

    $this->getJson(
        "/api/v1/customers/{$storeBCustomerId}",
        array_merge(['Authorization' => "Bearer {$ownerToken}"], tenantHeader($this->storeA))
    )->assertStatus(404);
});

it('does not let a registered identity in one store authenticate against another store even with identical credentials', function () {
    // Registering the identical email+password combination fresh in
    // Store B creates a wholly separate CustomerIdentity row — logging
    // in on Store A's domain must never authenticate as the Store B
    // identity or vice versa.
    $this->postJson(storefrontUrl($this->hostB, '/api/v1/storefront/account/register'), [
        'email' => 'isolation-customer@example.test',
        'password' => 'correct-password-1',
    ])->assertCreated();

    $loginA = $this->postJson(storefrontUrl($this->hostA, '/api/v1/storefront/account/login'), [
        'email' => 'isolation-customer@example.test',
        'password' => 'correct-password-1',
    ])->assertOk();

    // The Store A access token must not authenticate on Store B.
    $this->getJson(storefrontUrl($this->hostB, '/api/v1/storefront/account'), authHeader($loginA->json('access_token')))
        ->assertStatus(401);
});
