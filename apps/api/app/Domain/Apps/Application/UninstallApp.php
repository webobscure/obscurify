<?php

namespace App\Domain\Apps\Application;

use App\Domain\Apps\Enums\InstalledAppStatus;
use App\Domain\Apps\Models\AppPermission;
use App\Domain\Apps\Models\AppToken;
use App\Domain\Apps\Models\InstalledApp;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;

/**
 * Uninstalling revokes every live token and permission and marks the
 * InstalledApp row itself uninstalled — never deletes it, so the audit
 * trail (Platform Events, AppToken/AppPermission history) survives.
 */
final class UninstallApp
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    public function handle(InstalledApp $installedApp): InstalledApp
    {
        return DB::transaction(function () use ($installedApp) {
            AppToken::query()
                ->where('installed_app_id', $installedApp->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            AppPermission::query()
                ->where('installed_app_id', $installedApp->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $installedApp->update([
                'status' => InstalledAppStatus::Uninstalled->value,
                'uninstalled_at' => now(),
            ]);

            $this->recordOutboxEvent->handle('AppUninstalled', 'InstalledApp', $installedApp->id, [
                'installed_app_id' => $installedApp->id,
                'store_id' => $installedApp->store_id,
                'app_id' => $installedApp->app_id,
            ]);

            return $installedApp;
        });
    }
}
