<?php

namespace App\Domain\Apps\Application;

use App\Domain\Apps\Enums\AppTokenType;
use App\Domain\Apps\Models\AppToken;
use App\Domain\Apps\Models\InstalledApp;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Str;

/**
 * Mints one access token + one refresh token for an InstalledApp,
 * always as a pair — used by both the initial code exchange and every
 * subsequent refresh (OAuth 2.1 refresh token rotation: a new refresh
 * token is issued every time the old one is spent, never reused).
 * Plaintext values are returned once; only their SHA-256 hashes are
 * persisted.
 */
final class IssueAppTokenPair
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    /**
     * @param  string[]  $scope
     * @return array{access_token: string, refresh_token: string, access: AppToken, refresh: AppToken}
     */
    public function handle(InstalledApp $installedApp, array $scope, ?AppToken $rotatedFromRefreshToken = null): array
    {
        $accessTokenPlain = Str::random(64);
        $refreshTokenPlain = Str::random(64);

        $access = AppToken::query()->create([
            'installed_app_id' => $installedApp->id,
            'type' => AppTokenType::Access->value,
            'token_hash' => hash('sha256', $accessTokenPlain),
            'scope' => $scope,
            'expires_at' => now()->addMinutes((int) config('apps.oauth.access_token_ttl_minutes')),
        ]);

        $refresh = AppToken::query()->create([
            'installed_app_id' => $installedApp->id,
            'rotated_from_id' => $rotatedFromRefreshToken?->id,
            'type' => AppTokenType::Refresh->value,
            'token_hash' => hash('sha256', $refreshTokenPlain),
            'scope' => $scope,
            'expires_at' => now()->addDays((int) config('apps.oauth.refresh_token_ttl_days')),
        ]);

        $this->recordOutboxEvent->handle('AppTokenCreated', 'InstalledApp', $installedApp->id, [
            'installed_app_id' => $installedApp->id,
            'store_id' => $installedApp->store_id,
            'access_token_id' => $access->id,
            'refresh_token_id' => $refresh->id,
            'scope' => $scope,
        ]);

        return [
            'access_token' => $accessTokenPlain,
            'refresh_token' => $refreshTokenPlain,
            'access' => $access,
            'refresh' => $refresh,
        ];
    }
}
