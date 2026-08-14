<?php

namespace App\Domain\Apps\Support;

use App\Domain\Apps\Models\AppToken;
use App\Domain\Apps\Models\InstalledApp;
use RuntimeException;

/**
 * Holds the current request's authenticated app-token identity — the
 * `/api/apps/v1` gateway's analogue of TenantContext, bound as a
 * singleton and populated by AuthenticateAppToken. `EnsureAppScope`
 * reads it to enforce scopes; gateway controllers read it to know which
 * InstalledApp is calling.
 */
final class CurrentAppContext
{
    private ?InstalledApp $installedApp = null;

    private ?AppToken $token = null;

    public function set(InstalledApp $installedApp, AppToken $token): void
    {
        $this->installedApp = $installedApp;
        $this->token = $token;
    }

    public function clear(): void
    {
        $this->installedApp = null;
        $this->token = null;
    }

    public function installedApp(): InstalledApp
    {
        if ($this->installedApp === null) {
            throw new RuntimeException('No authenticated app token is active.');
        }

        return $this->installedApp;
    }

    public function token(): AppToken
    {
        if ($this->token === null) {
            throw new RuntimeException('No authenticated app token is active.');
        }

        return $this->token;
    }

    public function hasScope(string $scope): bool
    {
        return $this->token !== null && in_array($scope, $this->token->scope, true);
    }
}
