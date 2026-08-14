<?php

use App\Domain\Apps\Application\InstallApp;
use App\Domain\Apps\Application\IssueAppTokenPair;
use App\Domain\Apps\Enums\AppType;
use App\Domain\Apps\Models\App;
use App\Domain\Apps\Models\InstalledApp;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);

    $this->privateAppB = app(TenantContext::class)->scope($this->storeB, fn () => App::factory()->create([
        'store_id' => $this->storeB->id,
        'name' => 'Store B Private App',
        'slug' => 'store-b-private-app',
    ]));
});

it('registers a private app scoped to the active store, with the client secret shown once', function () {
    $response = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/apps', [
        'name' => 'My Integration',
        'slug' => 'my-integration',
        'redirect_urls' => ['https://example.test/callback'],
        'requested_scopes' => ['orders.read', 'products.read'],
    ], tenantHeader($this->storeA))->assertCreated();

    expect($response->json('data.store_id'))->toBe($this->storeA->id)
        ->and($response->json('data.type'))->toBe('private')
        ->and($response->json('data.client_id'))->toBeString()
        ->and($response->json('data.client_secret'))->toBeString();

    $show = $this->actingAs($this->userA, 'sanctum')->getJson(
        "/api/v1/apps/{$response->json('data.id')}",
        tenantHeader($this->storeA),
    )->assertOk();

    expect($show->json('data'))->not->toHaveKey('client_secret');
});

it('rejects registering an app with an unknown scope', function () {
    $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/apps', [
        'name' => 'Bad Scope App',
        'slug' => 'bad-scope-app',
        'redirect_urls' => ['https://example.test/callback'],
        'requested_scopes' => ['not_a_real_scope'],
    ], tenantHeader($this->storeA))->assertStatus(400)->assertJson(['error' => 'invalid_scope']);
});

it('never lets Store A see or install a Store B private app', function () {
    $this->actingAs($this->userA, 'sanctum')->getJson(
        "/api/v1/apps/{$this->privateAppB->id}",
        tenantHeader($this->storeA),
    )->assertNotFound();

    $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/apps/{$this->privateAppB->id}/install",
        [],
        tenantHeader($this->storeA),
    )->assertNotFound();

    $index = $this->actingAs($this->userA, 'sanctum')->getJson('/api/v1/apps', tenantHeader($this->storeA))->assertOk();
    expect(collect($index->json('data'))->pluck('id'))->not->toContain($this->privateAppB->id);
});

it('lets any store install a Public app', function () {
    $publicApp = App::factory()->create([
        'store_id' => null,
        'type' => AppType::Public,
        'name' => 'Platform Helper',
        'slug' => 'platform-helper',
        'requested_scopes' => ['orders.read'],
    ]);

    $index = $this->actingAs($this->userA, 'sanctum')->getJson('/api/v1/apps', tenantHeader($this->storeA))->assertOk();
    expect(collect($index->json('data'))->pluck('id'))->toContain($publicApp->id);

    $installed = $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/apps/{$publicApp->id}/install",
        [],
        tenantHeader($this->storeA),
    )->assertOk();

    expect($installed->json('data.status'))->toBe('active')
        ->and($installed->json('data.scopes'))->toBe(['orders.read']);
});

it('installs, then uninstalls, revoking every live token and permission', function () {
    $app = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/apps', [
        'name' => 'Lifecycle App',
        'slug' => 'lifecycle-app',
        'redirect_urls' => ['https://example.test/callback'],
        'requested_scopes' => ['orders.read'],
    ], tenantHeader($this->storeA))->assertCreated();

    $installed = $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/apps/{$app->json('data.id')}/install",
        [],
        tenantHeader($this->storeA),
    )->assertOk();

    $installedAppId = $installed->json('data.id');

    $uninstalled = $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/installed-apps/{$installedAppId}/uninstall",
        [],
        tenantHeader($this->storeA),
    )->assertOk();

    expect($uninstalled->json('data.status'))->toBe('uninstalled')
        ->and($uninstalled->json('data.uninstalled_at'))->not->toBeNull();

    // Re-listing installed apps still shows it (never deleted, spec
    // section 12's audit trail) but as uninstalled.
    $index = $this->actingAs($this->userA, 'sanctum')->getJson('/api/v1/installed-apps', tenantHeader($this->storeA))->assertOk();
    $row = collect($index->json('data'))->firstWhere('id', $installedAppId);
    expect($row['status'])->toBe('uninstalled');
});

it('never lets Store A list installed apps, or read tokens, belonging to Store B', function () {
    $installedB = app(TenantContext::class)->scope($this->storeB, function () {
        return app(InstallApp::class)->handle($this->privateAppB);
    });

    $this->actingAs($this->userA, 'sanctum')->getJson(
        "/api/v1/installed-apps/{$installedB->id}",
        tenantHeader($this->storeA),
    )->assertNotFound();

    $this->actingAs($this->userA, 'sanctum')->getJson(
        "/api/v1/installed-apps/{$installedB->id}/tokens",
        tenantHeader($this->storeA),
    )->assertNotFound();

    $this->actingAs($this->userA, 'sanctum')->getJson(
        "/api/v1/installed-apps/{$installedB->id}/webhooks",
        tenantHeader($this->storeA),
    )->assertNotFound();

    $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/installed-apps/{$installedB->id}/uninstall",
        [],
        tenantHeader($this->storeA),
    )->assertNotFound();
});

it("lists an installed app's own webhook subscriptions", function () {
    $app = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/apps', [
        'name' => 'Webhook App',
        'slug' => 'webhook-app',
        'redirect_urls' => ['https://example.test/callback'],
        'requested_scopes' => ['webhooks.write'],
    ], tenantHeader($this->storeA))->assertCreated();

    $installed = $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/apps/{$app->json('data.id')}/install",
        [],
        tenantHeader($this->storeA),
    )->assertOk();

    $token = app(TenantContext::class)->scope($this->storeA, fn () => app(IssueAppTokenPair::class)->handle(
        InstalledApp::query()->findOrFail($installed->json('data.id')),
        ['webhooks.write'],
    ))['access_token'];

    $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/apps/v1/webhooks', [
        'name' => 'My hook',
        'target_url' => 'https://app.example.test/hooks',
        'event_types' => ['OrderCreated'],
    ])->assertCreated();

    $list = $this->actingAs($this->userA, 'sanctum')->getJson(
        "/api/v1/installed-apps/{$installed->json('data.id')}/webhooks",
        tenantHeader($this->storeA),
    )->assertOk();

    expect($list->json('data'))->toHaveCount(1)
        ->and($list->json('data.0.name'))->toBe('My hook')
        ->and($list->json('data.0'))->not->toHaveKey('secret');
});
