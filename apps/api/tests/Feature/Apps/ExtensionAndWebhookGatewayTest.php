<?php

use App\Domain\Apps\Application\InstallApp;
use App\Domain\Apps\Application\IssueAppTokenPair;
use App\Domain\Apps\Models\App;
use App\Domain\Webhooks\Models\WebhookSubscription;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

/**
 * Installs an app and mints a usable access token directly through the
 * application layer — the full HTTP OAuth dance is already covered end
 * to end in OAuthFlowTest; these tests are about what an authenticated
 * app can *do*, not how it authenticated.
 */
function issueGatewayAccessToken(App $app, array $scopes): string
{
    $installedApp = app(InstallApp::class)->handle($app, $scopes);
    $issued = app(IssueAppTokenPair::class)->handle($installedApp, $scopes);

    return $issued['access_token'];
}

it('lets an app register its own webhook subscription, owned by the installation, reusing the platform delivery engine', function () {
    $app = app(TenantContext::class)->scope($this->store, fn () => App::factory()->create(['requested_scopes' => ['webhooks.write']]));
    $token = app(TenantContext::class)->scope($this->store, fn () => issueGatewayAccessToken($app, ['webhooks.write']));

    $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/apps/v1/webhooks', [
        'name' => 'My app hook',
        'target_url' => 'https://app.example.test/hooks',
        'event_types' => ['OrderCreated'],
    ])->assertCreated();

    expect($response->json('data.secret'))->toBeString();

    app(TenantContext::class)->scope($this->store, function () {
        $subscription = WebhookSubscription::query()->where('owner_type', 'app')->firstOrFail();
        expect($subscription->owner_id)->not->toBeNull()
            ->and($subscription->event_types)->toBe(['OrderCreated']);
    });
});

it('rejects registering a webhook subscription without the webhooks.write scope', function () {
    $app = app(TenantContext::class)->scope($this->store, fn () => App::factory()->create(['requested_scopes' => ['orders.read']]));
    $token = app(TenantContext::class)->scope($this->store, fn () => issueGatewayAccessToken($app, ['orders.read']));

    $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/apps/v1/webhooks', [
        'name' => 'Nope',
        'target_url' => 'https://app.example.test/hooks',
        'event_types' => ['OrderCreated'],
    ])->assertStatus(403)->assertJson(['error' => 'insufficient_scope']);
});

it('registers an admin_navigation extension and the admin surface can read it back', function () {
    $app = app(TenantContext::class)->scope($this->store, fn () => App::factory()->create(['requested_scopes' => ['orders.read']]));
    $token = app(TenantContext::class)->scope($this->store, fn () => issueGatewayAccessToken($app, ['orders.read']));

    $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/apps/v1/extensions', [
        'extension_point' => 'admin_navigation',
        'config' => ['label' => 'My App', 'path' => '/apps/my-app', 'icon' => 'puzzle'],
    ])->assertCreated()->assertJson(['data' => ['extension_point' => 'admin_navigation']]);

    $admin = $this->actingAs($this->user, 'sanctum')->getJson(
        '/api/v1/admin-extensions?point=admin_navigation',
        tenantHeader($this->store),
    )->assertOk();

    expect($admin->json('data'))->toHaveCount(1)
        ->and($admin->json('data.0.config.label'))->toBe('My App');
});

it('rejects registering an extension missing its required config keys', function () {
    $app = app(TenantContext::class)->scope($this->store, fn () => App::factory()->create(['requested_scopes' => ['orders.read']]));
    $token = app(TenantContext::class)->scope($this->store, fn () => issueGatewayAccessToken($app, ['orders.read']));

    $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/apps/v1/extensions', [
        'extension_point' => 'dashboard_card',
        'config' => ['not_title' => 'oops'],
    ])->assertStatus(422);
});
