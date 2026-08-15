<?php

use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerIdentity;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use App\Shared\Commerce\Models\OutboxEvent;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->host = 'e2e-register.localhost';
    domainForStore($this->store, $this->host);
});

it('registers a new customer, auto-logs them in, and publishes CustomerCreated', function () {
    $response = $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/register'), [
        'email' => 'new-customer@example.test',
        'password' => 'super-secret-1',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ])->assertCreated();

    $response->assertJsonPath('data.email', 'new-customer@example.test')
        ->assertJsonPath('data.first_name', 'Ada')
        ->assertJsonPath('data.status', 'active');

    expect($response->json('access_token'))->toBeString()
        ->and($response->json('refresh_token'))->toBeString();

    app(TenantContext::class)->scope($this->store, function () {
        expect(Customer::query()->where('email', 'new-customer@example.test')->count())->toBe(1);
        expect(CustomerIdentity::query()->where('identifier', 'new-customer@example.test')->count())->toBe(1);
        expect(OutboxEvent::query()->where('event_type', 'CustomerCreated')->count())->toBe(1);
    });
});

it('rejects registering the same email twice in the same store', function () {
    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/register'), [
        'email' => 'dup@example.test',
        'password' => 'super-secret-1',
    ])->assertCreated();

    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/register'), [
        'email' => 'dup@example.test',
        'password' => 'another-secret-1',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('merges a prior guest checkout customer record by email instead of creating a duplicate', function () {
    $guestCustomer = app(TenantContext::class)->scope($this->store, function () {
        return Customer::factory()->create(['email' => 'guest-turned-account@example.test']);
    });

    $order = app(TenantContext::class)->scope($this->store, function () use ($guestCustomer) {
        return Order::factory()->create(['customer_id' => $guestCustomer->id]);
    });

    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/register'), [
        'email' => 'guest-turned-account@example.test',
        'password' => 'super-secret-1',
    ])->assertCreated();

    app(TenantContext::class)->scope($this->store, function () use ($guestCustomer, $order) {
        expect(Customer::query()->where('email', 'guest-turned-account@example.test')->count())->toBe(1);

        $identity = CustomerIdentity::query()->where('identifier', 'guest-turned-account@example.test')->firstOrFail();
        expect($identity->customer_id)->toBe($guestCustomer->id);
        expect(Order::query()->find($order->id)->customer_id)->toBe($guestCustomer->id);
    });
});

it('allows the same email to register as a separate identity in a different store', function () {
    $otherStore = createStoreForUser($this->user, ['slug' => 'other-store-register']);
    $otherHost = 'e2e-register-other.localhost';
    domainForStore($otherStore, $otherHost);

    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/register'), [
        'email' => 'cross-store@example.test',
        'password' => 'super-secret-1',
    ])->assertCreated();

    // Registering the *same* email in a different store must succeed —
    // spec section 4: "Same email may exist in different stores."
    $this->postJson(storefrontUrl($otherHost, '/api/v1/storefront/account/register'), [
        'email' => 'cross-store@example.test',
        'password' => 'different-secret-1',
    ])->assertCreated();

    app(TenantContext::class)->scope($this->store, function () {
        expect(CustomerIdentity::query()->where('identifier', 'cross-store@example.test')->count())->toBe(1);
    });
    app(TenantContext::class)->scope($otherStore, function () {
        expect(CustomerIdentity::query()->where('identifier', 'cross-store@example.test')->count())->toBe(1);
    });
});
