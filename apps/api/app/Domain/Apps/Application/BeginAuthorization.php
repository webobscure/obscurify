<?php

namespace App\Domain\Apps\Application;

use App\Domain\Apps\Exceptions\OAuthErrorException;
use App\Domain\Apps\Models\OAuthAuthorization;
use App\Domain\Apps\Models\OAuthClient;
use App\Domain\Apps\Support\AppScope;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The consent step (OAuth 2.1 Authorization Code + PKCE — spec section
 * 3, no implicit flow). Called while the merchant is authenticated in
 * the admin (TenantContext already set by the `tenant` middleware from
 * their active store) — installs the app for that store (if not already
 * installed) and grants exactly the scopes requested in *this*
 * authorization request, which may be a subset of the App's own
 * `requested_scopes`.
 *
 * `code_challenge_method` must be `S256` — OAuth 2.1 drops the
 * `plain` PKCE method entirely, so this rejects it outright rather than
 * silently downgrading.
 */
final class BeginAuthorization
{
    public function __construct(
        private readonly InstallApp $installApp,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    /**
     * @param  string[]  $scopes
     * @return array{authorization: OAuthAuthorization, code: string}
     */
    public function handle(string $clientId, string $redirectUri, array $scopes, string $codeChallenge, string $codeChallengeMethod): array
    {
        $client = OAuthClient::query()->where('client_id', $clientId)->with('app')->first();

        if ($client === null || $client->app === null) {
            throw OAuthErrorException::invalidClient('Unknown client_id.');
        }

        if (! in_array($redirectUri, $client->app->redirect_urls, true)) {
            throw OAuthErrorException::invalidRequest('redirect_uri is not registered for this client.');
        }

        if ($codeChallengeMethod !== 'S256') {
            throw OAuthErrorException::invalidRequest('code_challenge_method must be S256.');
        }

        if ($codeChallenge === '') {
            throw OAuthErrorException::invalidRequest('code_challenge is required.');
        }

        foreach ($scopes as $scope) {
            if (! AppScope::isKnown($scope)) {
                throw OAuthErrorException::invalidScope("Unknown scope: {$scope}");
            }
        }

        return DB::transaction(function () use ($client, $redirectUri, $scopes, $codeChallenge, $codeChallengeMethod) {
            $installedApp = $this->installApp->handle($client->app, $scopes);

            $code = Str::random(64);

            $authorization = OAuthAuthorization::query()->create([
                'oauth_client_id' => $client->id,
                'installed_app_id' => $installedApp->id,
                'code_hash' => hash('sha256', $code),
                'code_challenge' => $codeChallenge,
                'code_challenge_method' => $codeChallengeMethod,
                'redirect_uri' => $redirectUri,
                'scope' => $scopes,
                'expires_at' => now()->addMinutes((int) config('apps.oauth.authorization_code_ttl_minutes')),
            ]);

            $this->recordOutboxEvent->handle('OAuthAuthorizationGranted', 'InstalledApp', $installedApp->id, [
                'installed_app_id' => $installedApp->id,
                'store_id' => $installedApp->store_id,
                'app_id' => $client->app_id,
                'scope' => $scopes,
            ]);

            return ['authorization' => $authorization, 'code' => $code];
        });
    }
}
