<?php

namespace App\Domain\Apps\Application;

use App\Domain\Apps\Exceptions\OAuthErrorException;
use App\Domain\Apps\Models\AppToken;
use App\Domain\Apps\Models\OAuthClient;
use App\Domain\Stores\Models\Store;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Hash;

/**
 * RFC 7009-style revocation — works for either an access or a refresh
 * token. Revoking a refresh token does not cascade to its already-issued
 * access token (that one simply expires on its own short TTL); revoking
 * an access token does not affect the refresh token that issued it.
 * Unknown-token requests still return success (per RFC 7009 §2.2, so a
 * caller can't probe for which tokens exist).
 */
final class RevokeAppToken
{
    public function __construct(
        private readonly RecordOutboxEvent $recordOutboxEvent,
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(string $clientId, string $clientSecret, string $token): void
    {
        $client = OAuthClient::query()->where('client_id', $clientId)->first();

        if ($client === null || ! Hash::check($clientSecret, $client->client_secret_hash)) {
            throw OAuthErrorException::invalidClient();
        }

        $tokenHash = hash('sha256', $token);

        $appToken = AppToken::withoutGlobalScopes()->where('token_hash', $tokenHash)->first();

        if ($appToken === null || $appToken->revoked_at !== null) {
            return;
        }

        $store = Store::query()->find($appToken->store_id);

        $this->tenantContext->scope($store, function () use ($appToken) {
            $appToken->update(['revoked_at' => now()]);

            $this->recordOutboxEvent->handle('AppTokenRevoked', 'InstalledApp', $appToken->installed_app_id, [
                'installed_app_id' => $appToken->installed_app_id,
                'store_id' => $appToken->store_id,
                'token_id' => $appToken->id,
                'token_type' => $appToken->type->value,
            ]);
        });
    }
}
