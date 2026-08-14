<?php

namespace App\Domain\Apps\Application;

use App\Domain\Apps\Enums\InstalledAppStatus;
use App\Domain\Apps\Exceptions\OAuthErrorException;
use App\Domain\Apps\Models\App;
use App\Domain\Apps\Models\AppPermission;
use App\Domain\Apps\Models\InstalledApp;
use App\Domain\Apps\Support\AppScope;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;

/**
 * Installs an App into the active store — idempotent (re-installing an
 * already-`active` install is a no-op besides granting any newly
 * requested scopes) and reinstalls a previously-uninstalled row rather
 * than creating a second one, so `AppToken`/`AppPermission` history for
 * that (store, app) pair stays on one `InstalledApp` id.
 */
final class InstallApp
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    public function handle(App $app, ?array $scopes = null): InstalledApp
    {
        $scopes ??= $app->requested_scopes;

        foreach ($scopes as $scope) {
            if (! AppScope::isKnown($scope)) {
                throw OAuthErrorException::invalidScope("Unknown scope: {$scope}");
            }
        }

        return DB::transaction(function () use ($app, $scopes) {
            $installedApp = InstalledApp::query()->firstOrCreate(
                ['app_id' => $app->id],
                ['status' => InstalledAppStatus::Active->value, 'installed_at' => now()],
            );

            if ($installedApp->status !== InstalledAppStatus::Active) {
                $installedApp->update(['status' => InstalledAppStatus::Active->value, 'installed_at' => now(), 'uninstalled_at' => null]);
            }

            $existingScopes = $installedApp->activeScopes();

            foreach ($scopes as $scope) {
                if (! in_array($scope, $existingScopes, true)) {
                    AppPermission::query()->create([
                        'installed_app_id' => $installedApp->id,
                        'scope' => $scope,
                        'granted_at' => now(),
                    ]);
                }
            }

            $this->recordOutboxEvent->handle('AppInstalled', 'InstalledApp', $installedApp->id, [
                'installed_app_id' => $installedApp->id,
                'store_id' => $installedApp->store_id,
                'app_id' => $app->id,
                'scopes' => $scopes,
            ]);

            return $installedApp->fresh(['permissions']);
        });
    }
}
