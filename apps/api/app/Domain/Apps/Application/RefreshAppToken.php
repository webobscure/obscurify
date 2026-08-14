<?php

namespace App\Domain\Apps\Application;

use App\Domain\Apps\Enums\AppTokenType;
use App\Domain\Apps\Exceptions\OAuthErrorException;
use App\Domain\Apps\Models\AppToken;
use App\Domain\Apps\Models\InstalledApp;
use App\Domain\Apps\Models\OAuthClient;
use App\Domain\Stores\Models\Store;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * `grant_type=refresh_token` — OAuth 2.1 refresh token rotation: every
 * refresh consumes the presented token and issues a brand new
 * access+refresh pair (`IssueAppTokenPair`), chained via
 * `rotated_from_id`. If a refresh token that's *already revoked* (i.e.
 * already spent by an earlier rotation, or manually revoked) is
 * presented again, that's a reuse signal — a stolen token being used
 * alongside the legitimate one — and the security response is to revoke
 * every token the InstalledApp holds, forcing a fresh authorization
 * rather than trusting anything already issued.
 *
 * The reuse-detection revocation must commit even though the request
 * itself ends in an error — so the rejection is thrown *after*
 * DB::transaction() returns, never from inside it (throwing inside would
 * roll the revocation back along with everything else).
 */
final class RefreshAppToken
{
    public function __construct(
        private readonly IssueAppTokenPair $issueAppTokenPair,
        private readonly RecordOutboxEvent $recordOutboxEvent,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, scope: string}
     */
    public function handle(string $clientId, string $clientSecret, string $refreshToken): array
    {
        $client = OAuthClient::query()->where('client_id', $clientId)->first();

        if ($client === null || ! Hash::check($clientSecret, $client->client_secret_hash)) {
            throw OAuthErrorException::invalidClient();
        }

        $tokenHash = hash('sha256', $refreshToken);

        $outcome = DB::transaction(function () use ($client, $tokenHash) {
            $token = AppToken::withoutGlobalScopes()
                ->where('token_hash', $tokenHash)
                ->where('type', AppTokenType::Refresh->value)
                ->lockForUpdate()
                ->first();

            if ($token === null) {
                return ['ok' => false, 'reason' => 'not_found'];
            }

            $installedApp = InstalledApp::withoutGlobalScopes()->find($token->installed_app_id);

            if ($installedApp === null || $installedApp->app_id !== $client->app_id) {
                return ['ok' => false, 'reason' => 'not_found'];
            }

            $store = Store::query()->find($token->store_id);

            return $this->tenantContext->scope($store, function () use ($token, $installedApp) {
                if ($token->isRevoked()) {
                    $this->revokeAllTokens($installedApp);

                    return ['ok' => false, 'reason' => 'reused'];
                }

                if ($token->isExpired()) {
                    return ['ok' => false, 'reason' => 'expired'];
                }

                $token->update(['revoked_at' => now()]);
                $issued = $this->issueAppTokenPair->handle($installedApp, $token->scope, $token);

                return ['ok' => true, 'issued' => $issued, 'scope' => $token->scope];
            });
        });

        if (! $outcome['ok']) {
            throw match ($outcome['reason']) {
                'reused' => OAuthErrorException::invalidGrant('This refresh token has already been used — all tokens for this installation have been revoked as a precaution.'),
                'expired' => OAuthErrorException::invalidGrant('This refresh token has expired.'),
                default => OAuthErrorException::invalidGrant(),
            };
        }

        return [
            'access_token' => $outcome['issued']['access_token'],
            'refresh_token' => $outcome['issued']['refresh_token'],
            'token_type' => 'Bearer',
            'expires_in' => (int) config('apps.oauth.access_token_ttl_minutes') * 60,
            'scope' => implode(' ', $outcome['scope']),
        ];
    }

    private function revokeAllTokens(InstalledApp $installedApp): void
    {
        AppToken::query()
            ->where('installed_app_id', $installedApp->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $this->recordOutboxEvent->handle('AppTokenReuseDetected', 'InstalledApp', $installedApp->id, [
            'installed_app_id' => $installedApp->id,
            'store_id' => $installedApp->store_id,
        ]);
    }
}
