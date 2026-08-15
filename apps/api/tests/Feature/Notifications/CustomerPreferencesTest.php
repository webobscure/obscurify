<?php

use App\Domain\Customers\Models\Customer;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Routing\Middleware\ThrottleRequests;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->host = 'e2e-notifications.localhost';
    domainForStore($this->store, $this->host);
    $this->withoutMiddleware(ThrottleRequests::class);
});

it('returns sensible defaults for a customer with no preference row yet', function () {
    $customerId = app(TenantContext::class)->scope($this->store, fn () => Customer::factory()->create()->id);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/customers/{$customerId}/notification-preferences", tenantHeader($this->store))
        ->assertOk();

    expect($response->json('data.email_enabled'))->toBeTrue();
    expect($response->json('data.sms_enabled'))->toBeFalse();
    expect($response->json('data.push_enabled'))->toBeFalse();
});

it('lets an admin update a customer preference on their behalf, upserting the row', function () {
    $customerId = app(TenantContext::class)->scope($this->store, fn () => Customer::factory()->create()->id);

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/v1/customers/{$customerId}/notification-preferences", ['sms_enabled' => true, 'marketing_opt_in' => true], tenantHeader($this->store))
        ->assertOk()
        ->assertJsonPath('data.sms_enabled', true)
        ->assertJsonPath('data.marketing_opt_in', true)
        ->assertJsonPath('data.email_enabled', true);

    app(TenantContext::class)->scope($this->store, function () use ($customerId) {
        expect(NotificationPreference::query()->where('customer_id', $customerId)->count())->toBe(1);
    });
});

it('lets a customer read and update their own preferences through the portal', function () {
    $registered = $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/register'), [
        'email' => 'prefs-test@example.test',
        'password' => 'correct-password-1',
    ])->assertCreated();

    $token = $registered->json('access_token');

    $this->getJson(storefrontUrl($this->host, '/api/v1/storefront/account/notification-preferences'), authHeader($token))
        ->assertOk()
        ->assertJsonPath('data.email_enabled', true);

    $this->patchJson(storefrontUrl($this->host, '/api/v1/storefront/account/notification-preferences'), ['email_enabled' => false, 'quiet_hours_start' => '22:00'], authHeader($token))
        ->assertOk()
        ->assertJsonPath('data.email_enabled', false)
        ->assertJsonPath('data.quiet_hours_start', '22:00');
});
