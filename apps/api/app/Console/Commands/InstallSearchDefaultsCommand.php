<?php

namespace App\Console\Commands;

use App\Domain\Search\Application\EnsureDefaultSearchSetup;
use App\Domain\Stores\Models\Store;
use Illuminate\Console\Command;

/**
 * Idempotent, safe to re-run after a platform upgrade — matches
 * `notifications:install`/`automation:install`'s own convention.
 */
class InstallSearchDefaultsCommand extends Command
{
    protected $signature = 'search:install';

    protected $description = 'Seed the default database search provider and settings for every store';

    public function handle(EnsureDefaultSearchSetup $ensureDefaults): int
    {
        $count = 0;

        Store::query()->cursor()->each(function (Store $store) use ($ensureDefaults, &$count) {
            $ensureDefaults->handle($store);
            $count++;
        });

        $this->info("Ensured default search setup for {$count} store(s).");

        return self::SUCCESS;
    }
}
