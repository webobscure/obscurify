<?php

use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerIdentity;
use App\Models\User;
use App\Shared\Commerce\Models\OutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Routing\Middleware\ThrottleRequests;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->host = 'e2e-login.localhost';
    domainForStore($this->store, $this->host);

    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/register'), [
        'email' => 'login-test@example.test',
        'password' => 'correct-password-1',
    ])->assertCreated();
});

it('logs in with correct credentials and publishes CustomerLoggedIn', function () {
    $response = $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/login'), [
        'email' => 'login-test@example.test',
        'password' => 'correct-password-1',
    ])->assertOk();

    expect($response->json('access_token'))->toBeString();

    app(TenantContext::class)->scope($this->store, function () {
        expect(OutboxEvent::query()->where('event_type', 'CustomerLoggedIn')->count())->toBe(1);
    });
});

it('rejects a wrong password with a generic message', function () {
    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/login'), [
        'email' => 'login-test@example.test',
        'password' => 'wrong-password',
    ])->assertStatus(401)->assertJsonPath('error', 'unauthorized');
});

it('rejects login for an email that was never registered, with the identical message', function () {
    $unknown = $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/login'), [
        'email' => 'never-registered@example.test',
        'password' => 'whatever-1',
    ])->assertStatus(401);

    $wrongPassword = $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/login'), [
        'email' => 'login-test@example.test',
        'password' => 'wrong-password',
    ])->assertStatus(401);

    expect($unknown->json('message'))->toBe($wrongPassword->json('message'));
});

it('locks the account after the configured number of failed attempts and rejects even the correct password while locked', function () {
    // This test's whole point is the lockout counter, not the
    // throttle:customer-auth rate limiter (5/minute/IP) — exceeding
    // max_failed_attempts already means more than 5 requests once the
    // setup register call is counted too, so the two guards would
    // otherwise collide.
    $this->withoutMiddleware(ThrottleRequests::class);

    $max = (int) config('customers.max_failed_attempts');

    for ($i = 0; $i < $max; $i++) {
        $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/login'), [
            'email' => 'login-test@example.test',
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    app(TenantContext::class)->scope($this->store, function () use ($max) {
        $identity = CustomerIdentity::query()->where('identifier', 'login-test@example.test')->firstOrFail();
        expect($identity->failed_attempts)->toBe($max);
        expect($identity->isLocked())->toBeTrue();
    });

    $response = $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/login'), [
        'email' => 'login-test@example.test',
        'password' => 'correct-password-1',
    ])->assertStatus(401);

    expect($response->json('message'))->toContain('locked');
});

it('resets the failed attempt counter after a successful login', function () {
    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/login'), [
        'email' => 'login-test@example.test',
        'password' => 'wrong-password',
    ])->assertStatus(401);

    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/login'), [
        'email' => 'login-test@example.test',
        'password' => 'correct-password-1',
    ])->assertOk();

    app(TenantContext::class)->scope($this->store, function () {
        $identity = CustomerIdentity::query()->where('identifier', 'login-test@example.test')->firstOrFail();
        expect($identity->failed_attempts)->toBe(0);
    });
});

it('rejects login for a disabled customer', function () {
    app(TenantContext::class)->scope($this->store, function () {
        Customer::query()
            ->where('email', 'login-test@example.test')
            ->update(['status' => 'disabled']);
    });

    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/login'), [
        'email' => 'login-test@example.test',
        'password' => 'correct-password-1',
    ])->assertStatus(401);
});
