<?php

namespace App\Domain\Apps\Application;

use App\Domain\Apps\Exceptions\OAuthErrorException;
use App\Domain\Apps\Models\InstalledApp;
use App\Domain\Apps\Models\OAuthAuthorization;
use App\Domain\Apps\Models\OAuthClient;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * `grant_type=authorization_code` — called by the third-party app's own
 * server, never by a browser: no Sanctum session, no `tenant`
 * middleware. Tenant is resolved from the claimed OAuthAuthorization's
 * own `store_id` and scoped manually for the duration of the exchange
 * (same reasoning as ProcessPaymentWebhook resolving tenant from a
 * webhook payload instead of a header).
 */
final class ExchangeAuthorizationCode
{
    public function __construct(
        private readonly IssueAppTokenPair $issueAppTokenPair,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, scope: string}
     */
    public function handle(string $clientId, string $clientSecret, string $code, string $codeVerifier, string $redirectUri): array
    {
        $client = OAuthClient::query()->where('client_id', $clientId)->first();

        if ($client === null || ! Hash::check($clientSecret, $client->client_secret_hash)) {
            throw OAuthErrorException::invalidClient();
        }

        $codeHash = hash('sha256', $code);

        return DB::transaction(function () use ($client, $codeHash, $codeVerifier, $redirectUri) {
            $authorization = OAuthAuthorization::withoutGlobalScopes()
                ->where('code_hash', $codeHash)
                ->lockForUpdate()
                ->first();

            if ($authorization === null || $authorization->oauth_client_id !== $client->id) {
                throw OAuthErrorException::invalidGrant();
            }

            if ($authorization->used_at !== null || $authorization->isExpired()) {
                throw OAuthErrorException::invalidGrant('This authorization code has already been used or has expired.');
            }

            if (! hash_equals($authorization->redirect_uri, $redirectUri)) {
                throw OAuthErrorException::invalidGrant('redirect_uri does not match the one used to obtain this code.');
            }

            if (! hash_equals($authorization->code_challenge, $this->pkceChallenge($codeVerifier))) {
                throw OAuthErrorException::invalidGrant('code_verifier does not match the original code_challenge.');
            }

            $authorization->update(['used_at' => now()]);

            $store = Store::query()->find($authorization->store_id);

            return $this->tenantContext->scope($store, function () use ($authorization) {
                $installedApp = InstalledApp::query()->findOrFail($authorization->installed_app_id);
                $issued = $this->issueAppTokenPair->handle($installedApp, $authorization->scope);

                return [
                    'access_token' => $issued['access_token'],
                    'refresh_token' => $issued['refresh_token'],
                    'token_type' => 'Bearer',
                    'expires_in' => (int) config('apps.oauth.access_token_ttl_minutes') * 60,
                    'scope' => implode(' ', $authorization->scope),
                ];
            });
        });
    }

    private function pkceChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }
}
