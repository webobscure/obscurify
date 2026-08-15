<?php

use App\Domain\Customers\Models\CustomerSession;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Routing\Middleware\ThrottleRequests;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->host = 'e2e-session.localhost';
    domainForStore($this->store, $this->host);
    $this->withoutMiddleware(ThrottleRequests::class);

    $registered = $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/register'), [
        'email' => 'session-test@example.test',
        'password' => 'correct-password-1',
    ])->assertCreated();

    $this->accessToken = $registered->json('access_token');
    $this->refreshToken = $registered->json('refresh_token');
});

it('accesses the account with a valid access token and rejects requests without one', function () {
    $this->getJson(storefrontUrl($this->host, '/api/v1/storefront/account'), authHeader($this->accessToken))
        ->assertOk()
        ->assertJsonPath('data.email', 'session-test@example.test');

    $this->getJson(storefrontUrl($this->host, '/api/v1/storefront/account'))
        ->assertStatus(401);

    $this->getJson(storefrontUrl($this->host, '/api/v1/storefront/account'), authHeader('not-a-real-token'))
        ->assertStatus(401);
});

it('rotates the refresh token on refresh and issues a usable new access token', function () {
    $response = $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/refresh'), [
        'refresh_token' => $this->refreshToken,
    ])->assertOk();

    $newAccessToken = $response->json('data.access_token');
    $newRefreshToken = $response->json('data.refresh_token');

    expect($newAccessToken)->not->toBe($this->accessToken);
    expect($newRefreshToken)->not->toBe($this->refreshToken);

    $this->getJson(storefrontUrl($this->host, '/api/v1/storefront/account'), authHeader($newAccessToken))
        ->assertOk();
});

it('detects refresh token reuse and revokes the whole session', function () {
    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/refresh'), [
        'refresh_token' => $this->refreshToken,
    ])->assertOk();

    // Reusing the already-spent refresh token is a theft signal.
    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/refresh'), [
        'refresh_token' => $this->refreshToken,
    ])->assertStatus(401);

    // The whole session — including the access token minted before
    // reuse was detected — is now revoked.
    $this->getJson(storefrontUrl($this->host, '/api/v1/storefront/account'), authHeader($this->accessToken))
        ->assertStatus(401);
});

it('revokes the access token on logout so it can no longer authenticate', function () {
    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/logout'), [], authHeader($this->accessToken))
        ->assertStatus(204);

    $this->getJson(storefrontUrl($this->host, '/api/v1/storefront/account'), authHeader($this->accessToken))
        ->assertStatus(401);
});

it('lists active sessions and lets a customer revoke another one of their own devices', function () {
    // A second "device" logging in.
    $secondLogin = $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/login'), [
        'email' => 'session-test@example.test',
        'password' => 'correct-password-1',
    ])->assertOk();

    $secondAccessToken = $secondLogin->json('access_token');

    $listResponse = $this->getJson(storefrontUrl($this->host, '/api/v1/storefront/account/sessions'), authHeader($this->accessToken))
        ->assertOk();

    expect($listResponse->json('data'))->toHaveCount(2);

    $otherSessionId = collect($listResponse->json('data'))->firstWhere('is_current', false)['id'];

    $this->deleteJson(storefrontUrl($this->host, "/api/v1/storefront/account/sessions/{$otherSessionId}"), [], authHeader($this->accessToken))
        ->assertStatus(204);

    // The revoked device's own access token no longer works.
    $this->getJson(storefrontUrl($this->host, '/api/v1/storefront/account'), authHeader($secondAccessToken))
        ->assertStatus(401);

    // The session that revoked it is unaffected.
    $this->getJson(storefrontUrl($this->host, '/api/v1/storefront/account'), authHeader($this->accessToken))
        ->assertOk();
});

it('rejects an access token whose session has expired', function () {
    app(TenantContext::class)->scope($this->store, function () {
        CustomerSession::query()->update(['expires_at' => now()->subMinute()]);
    });

    $this->getJson(storefrontUrl($this->host, '/api/v1/storefront/account'), authHeader($this->accessToken))
        ->assertStatus(401);
});

it('rejects an access token presented as a refresh token and vice versa', function () {
    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/refresh'), [
        'refresh_token' => $this->accessToken,
    ])->assertStatus(401);

    $this->getJson(storefrontUrl($this->host, '/api/v1/storefront/account'), authHeader($this->refreshToken))
        ->assertStatus(401);
});
