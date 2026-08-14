<?php

use App\Domain\Apps\Application\InstallApp;
use App\Domain\Apps\Application\RefreshAppToken;
use App\Domain\Apps\Enums\AppTokenType;
use App\Domain\Apps\Models\App;
use App\Domain\Apps\Models\AppToken;
use App\Domain\Apps\Models\OAuthClient;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * OAuth 2.1 refresh token reuse detection under real concurrent
 * PostgreSQL connections (spec section 13-equivalent rigor for Milestone
 * 12's tokens — see ReservationConcurrencyTest for the identical
 * fork-based pattern this mirrors). Exactly one of two simultaneous
 * refreshes of the *same* refresh token may succeed; the loser's reuse
 * detection must still win the race in the sense that no token for this
 * installation is left usable afterward.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->refreshTokenPlain = Str::random(64);

    [$this->registeredApp, $this->clientId] = app(TenantContext::class)->scope($this->store, function () {
        $app = App::factory()->create(['requested_scopes' => ['orders.read']]);
        $clientId = (string) Str::ulid();

        OAuthClient::query()->create([
            'app_id' => $app->id,
            'client_id' => $clientId,
            'client_secret_hash' => Hash::make('test-secret'),
        ]);

        $installedApp = app(InstallApp::class)->handle($app, ['orders.read']);

        AppToken::query()->create([
            'installed_app_id' => $installedApp->id,
            'type' => AppTokenType::Refresh->value,
            'token_hash' => hash('sha256', $this->refreshTokenPlain),
            'scope' => ['orders.read'],
            'expires_at' => now()->addDays(30),
        ]);

        return [$app, $clientId];
    });
});

afterEach(function () {
    DB::table('stores')->where('id', $this->store->id)->delete();
    DB::table('users')->where('id', $this->user->id)->delete();
});

it('lets exactly one of two simultaneous refreshes of the same token succeed, and revokes everything once reuse is detected', function () {
    $refresh = function () {
        return app(TenantContext::class)->scope($this->store, function () {
            $result = app(RefreshAppToken::class)->handle($this->clientId, 'test-secret', $this->refreshTokenPlain);

            return $result['access_token'];
        });
    };

    $results = runConcurrently([$refresh, $refresh]);

    $succeeded = array_filter($results, fn ($r) => $r['ok']);
    $failed = array_filter($results, fn ($r) => ! $r['ok']);

    expect($succeeded)->toHaveCount(1)
        ->and($failed)->toHaveCount(1);

    expect(current($failed)['error'])->toContain('already been used');

    // The winner's own newly-issued tokens are swept up too — reuse
    // detection assumes compromise and trusts nothing already issued.
    app(TenantContext::class)->scope($this->store, function () {
        expect(AppToken::query()->whereNull('revoked_at')->count())->toBe(0);
    });
});
