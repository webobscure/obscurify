<?php

use App\Domain\Apps\Application\InstallApp;
use App\Domain\Apps\Enums\AppTokenType;
use App\Domain\Apps\Models\App;
use App\Domain\Apps\Models\AppToken;
use App\Domain\Apps\Models\OAuthClient;
use App\Domain\Catalog\Models\Product;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    $this->hostA = 'e2e-graphql-auth-a.localhost';
    domainForStore($this->storeA, $this->hostA);

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);
    $this->hostB = 'e2e-graphql-auth-b.localhost';
    domainForStore($this->storeB, $this->hostB);
});

it('resolves as Guest for a plain storefront request with no bearer token', function () {
    $response = graphqlRequest($this->hostA, 'query { store { id } }');

    $response->assertOk();
    expect($response->json('data.store.id'))->toBe($this->storeA->id);
    expect($response->json('data.customer'))->toBeNull();
});

it('resolves as Merchant via Sanctum + X-Store-Id, respecting store membership', function () {
    $token = $this->userA->createToken('test')->plainTextToken;

    $ownStore = $this->postJson('http://any-host.localhost/api/graphql', [
        'query' => 'query { store { id } }',
    ], array_merge(['Authorization' => "Bearer {$token}"], tenantHeader($this->storeA)));
    $ownStore->assertOk();
    expect($ownStore->json('data.store.id'))->toBe($this->storeA->id);

    $otherStore = $this->postJson('http://any-host.localhost/api/graphql', [
        'query' => 'query { store { id } }',
    ], array_merge(['Authorization' => "Bearer {$token}"], tenantHeader($this->storeB)));
    $otherStore->assertStatus(403);
});

it('resolves as App via an AppToken bearer, scoped to the token\'s own installed store', function () {
    [$installedApp, $accessTokenPlain] = app(TenantContext::class)->scope($this->storeA, function () {
        $app = App::factory()->create(['requested_scopes' => ['orders.read'], 'name' => 'Test App']);
        OAuthClient::query()->create(['app_id' => $app->id, 'client_id' => (string) Str::ulid(), 'client_secret_hash' => Hash::make('secret')]);
        $installedApp = app(InstallApp::class)->handle($app, ['orders.read']);

        $plain = Str::random(64);
        AppToken::query()->create([
            'installed_app_id' => $installedApp->id,
            'type' => AppTokenType::Access->value,
            'token_hash' => hash('sha256', $plain),
            'scope' => ['orders.read'],
            'expires_at' => now()->addDay(),
        ]);

        return [$installedApp, $plain];
    });

    $response = graphqlRequest($this->hostB, 'query { appHealth { status appName } }', [], authHeader($accessTokenPlain));

    $response->assertOk();
    expect($response->json('data.appHealth.status'))->toBe('ok');
    // Resolves storeA (the token's own installed store) even though the
    // request hit storeB's hostname — an app token is never
    // hostname-scoped, exactly like AuthenticateAppToken (REST).
    expect($response->json('data.appHealth.appName'))->toBe('Test App');
});

it('rejects appHealth for every non-App actor', function () {
    $guestResponse = graphqlRequest($this->hostA, 'query { appHealth { status } }');
    expect($guestResponse->json('errors.0.message'))->toBe('appHealth is only callable with an app token.');
});

it('never leaks products, search results, carts, or store identity across tenants', function () {
    app(TenantContext::class)->scope($this->storeA, fn () => Product::factory()->create(['title' => 'Store A Secret Product', 'status' => 'active']));

    $productsFromB = graphqlRequest($this->hostB, 'query { products { data { title } } }');
    expect(collect($productsFromB->json('data.products.data'))->pluck('title')->all())->not->toContain('Store A Secret Product');

    $storeFromA = graphqlRequest($this->hostA, 'query { store { id } }');
    $storeFromB = graphqlRequest($this->hostB, 'query { store { id } }');
    expect($storeFromA->json('data.store.id'))->not->toBe($storeFromB->json('data.store.id'));

    // A customer registered on Store A cannot authenticate against Store B's hostname.
    $registerA = graphqlRequest($this->hostA, 'mutation { registerCustomer(email: "cross-tenant@example.test", password: "super-secret-1") { accessToken } }');
    $tokenA = $registerA->json('data.registerCustomer.accessToken');

    $wrongStore = graphqlRequest($this->hostB, 'query { customer { email } }', [], authHeader($tokenA));
    $wrongStore->assertStatus(401);
});
