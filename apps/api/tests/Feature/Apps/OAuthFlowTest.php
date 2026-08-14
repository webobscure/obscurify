<?php

use App\Domain\Apps\Models\App;
use App\Domain\Apps\Models\AppToken;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

/**
 * @return array{0: string, 1: string} [code_verifier, code_challenge]
 */
function pkcePair(): array
{
    $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    return [$verifier, $challenge];
}

function extractCode(string $redirectUrl): string
{
    parse_str((string) parse_url($redirectUrl, PHP_URL_QUERY), $query);

    return $query['code'];
}

it('completes the full OAuth 2.1 Authorization Code + PKCE flow and enforces granted scopes', function () {
    $registered = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/apps', [
        'name' => 'Order Sync',
        'slug' => 'order-sync',
        'redirect_urls' => ['https://app.example.test/callback'],
        'requested_scopes' => ['orders.read'],
    ], tenantHeader($this->store))->assertCreated();

    $clientId = $registered->json('data.client_id');
    $clientSecret = $registered->json('data.client_secret');
    expect($clientId)->toBeString()->and($clientSecret)->toBeString();

    [$verifier, $challenge] = pkcePair();

    $authorized = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/oauth/authorize', [
        'client_id' => $clientId,
        'redirect_uri' => 'https://app.example.test/callback',
        'scope' => 'orders.read',
        'state' => 'xyz',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ], tenantHeader($this->store))->assertOk();

    $redirectUrl = $authorized->json('data.redirect_url');
    expect($redirectUrl)->toStartWith('https://app.example.test/callback?')
        ->and($redirectUrl)->toContain('state=xyz');

    $code = extractCode($redirectUrl);

    // No auth header at all — this is the third-party app's own server
    // calling the platform directly.
    $tokenResponse = $this->postJson('/api/v1/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'code' => $code,
        'code_verifier' => $verifier,
        'redirect_uri' => 'https://app.example.test/callback',
    ])->assertOk();

    $accessToken = $tokenResponse->json('access_token');
    $refreshToken = $tokenResponse->json('refresh_token');
    expect($accessToken)->toBeString()->and($refreshToken)->toBeString()
        ->and($tokenResponse->json('token_type'))->toBe('Bearer')
        ->and($tokenResponse->json('scope'))->toBe('orders.read');

    // Replaying the same code must fail — single use.
    $this->postJson('/api/v1/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'code' => $code,
        'code_verifier' => $verifier,
        'redirect_uri' => 'https://app.example.test/callback',
    ])->assertStatus(400)->assertJson(['error' => 'invalid_grant']);

    // Granted scope succeeds...
    $this->withHeader('Authorization', "Bearer {$accessToken}")
        ->getJson('/api/apps/v1/orders')
        ->assertOk();

    // ...an ungranted scope is rejected, even with a perfectly valid token.
    $this->withHeader('Authorization', "Bearer {$accessToken}")
        ->getJson('/api/apps/v1/customers')
        ->assertStatus(403)
        ->assertJson(['error' => 'insufficient_scope']);

    // Refresh rotates: a brand new pair, and the old refresh token is
    // now spent.
    $refreshed = $this->postJson('/api/v1/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refreshToken,
    ])->assertOk();

    $newAccessToken = $refreshed->json('access_token');
    $newRefreshToken = $refreshed->json('refresh_token');
    expect($newAccessToken)->not->toBe($accessToken)
        ->and($newRefreshToken)->not->toBe($refreshToken);

    $this->withHeader('Authorization', "Bearer {$newAccessToken}")
        ->getJson('/api/apps/v1/orders')
        ->assertOk();

    // Reusing the already-rotated refresh token is a theft signal — it
    // must fail AND revoke every token this installation holds,
    // including the one just issued above.
    $this->postJson('/api/v1/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refreshToken,
    ])->assertStatus(400)->assertJson(['error' => 'invalid_grant']);

    $this->withHeader('Authorization', "Bearer {$newAccessToken}")
        ->getJson('/api/apps/v1/orders')
        ->assertStatus(401);

    app(TenantContext::class)->scope($this->store, function () {
        expect(AppToken::query()->whereNull('revoked_at')->count())->toBe(0);
    });
});

it('rejects a code exchange with the wrong code_verifier', function () {
    $registered = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/apps', [
        'name' => 'Bad PKCE App',
        'slug' => 'bad-pkce-app',
        'redirect_urls' => ['https://app.example.test/callback'],
    ], tenantHeader($this->store))->assertCreated();

    [$verifier, $challenge] = pkcePair();
    [$wrongVerifier] = pkcePair();

    $authorized = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/oauth/authorize', [
        'client_id' => $registered->json('data.client_id'),
        'redirect_uri' => 'https://app.example.test/callback',
        'scope' => 'orders.read',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ], tenantHeader($this->store))->assertOk();

    $code = extractCode($authorized->json('data.redirect_url'));

    $this->postJson('/api/v1/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $registered->json('data.client_id'),
        'client_secret' => $registered->json('data.client_secret'),
        'code' => $code,
        'code_verifier' => $wrongVerifier,
        'redirect_uri' => 'https://app.example.test/callback',
    ])->assertStatus(400)->assertJson(['error' => 'invalid_grant']);
});

it('rejects an authorize request for a redirect_uri the app never registered', function () {
    $registered = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/apps', [
        'name' => 'Strict Redirect App',
        'slug' => 'strict-redirect-app',
        'redirect_urls' => ['https://app.example.test/callback'],
    ], tenantHeader($this->store))->assertCreated();

    [, $challenge] = pkcePair();

    $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/oauth/authorize', [
        'client_id' => $registered->json('data.client_id'),
        'redirect_uri' => 'https://evil.example.test/callback',
        'scope' => 'orders.read',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ], tenantHeader($this->store))->assertStatus(400)->assertJson(['error' => 'invalid_request']);
});

it('rejects the plain PKCE method, only S256', function () {
    $registered = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/apps', [
        'name' => 'Plain PKCE App',
        'slug' => 'plain-pkce-app',
        'redirect_urls' => ['https://app.example.test/callback'],
    ], tenantHeader($this->store))->assertCreated();

    $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/oauth/authorize', [
        'client_id' => $registered->json('data.client_id'),
        'redirect_uri' => 'https://app.example.test/callback',
        'scope' => 'orders.read',
        'code_challenge' => 'somechallenge',
        'code_challenge_method' => 'plain',
    ], tenantHeader($this->store))->assertStatus(400)->assertJson(['error' => 'invalid_request']);
});

it('revokes a token so it can no longer authenticate the gateway', function () {
    $registered = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/apps', [
        'name' => 'Revoke Me App',
        'slug' => 'revoke-me-app',
        'redirect_urls' => ['https://app.example.test/callback'],
    ], tenantHeader($this->store))->assertCreated();

    [$verifier, $challenge] = pkcePair();

    $authorized = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/oauth/authorize', [
        'client_id' => $registered->json('data.client_id'),
        'redirect_uri' => 'https://app.example.test/callback',
        'scope' => 'orders.read',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ], tenantHeader($this->store))->assertOk();

    $code = extractCode($authorized->json('data.redirect_url'));

    $tokens = $this->postJson('/api/v1/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $registered->json('data.client_id'),
        'client_secret' => $registered->json('data.client_secret'),
        'code' => $code,
        'code_verifier' => $verifier,
        'redirect_uri' => 'https://app.example.test/callback',
    ])->assertOk();

    $this->postJson('/api/v1/oauth/revoke', [
        'client_id' => $registered->json('data.client_id'),
        'client_secret' => $registered->json('data.client_secret'),
        'token' => $tokens->json('access_token'),
    ])->assertOk();

    $this->withHeader('Authorization', "Bearer {$tokens->json('access_token')}")
        ->getJson('/api/apps/v1/orders')
        ->assertStatus(401);
});
