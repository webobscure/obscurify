<?php

namespace App\Console\Commands;

use App\Domain\Notifications\Application\EnsureDefaultNotificationSetup;
use App\Domain\Stores\Models\Store;
use Illuminate\Console\Command;

/**
 * Idempotent, safe to re-run after a platform upgrade — matches
 * `automation:install`/`analytics:install`'s own convention. Loops
 * every store (a fresh store gets its defaults lazily on first admin
 * Channels/Providers read anyway; this command exists for bulk
 * backfill/ops use).
 */
class InstallNotificationDefaultsCommand extends Command
{
    protected $signature = 'notifications:install';

    protected $description = 'Seed the default fake notification provider and channels for every store';

    public function handle(EnsureDefaultNotificationSetup $ensureDefaults): int
    {
        $count = 0;

        Store::query()->cursor()->each(function (Store $store) use ($ensureDefaults, &$count) {
            $ensureDefaults->handle($store);
            $count++;
        });

        $this->info("Ensured default notification setup for {$count} store(s).");

        return self::SUCCESS;
    }
}
