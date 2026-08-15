<?php

namespace App\Console\Commands;

use App\Domain\Search\Application\ReindexStore;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Bulk/ops full reindex across every store (or one, via --store=) —
 * the CLI counterpart to POST /search/reindex, which reindexes only
 * the active tenant. Not wired into Laravel's scheduler, matching this
 * codebase's "run externally on a cron" convention for every other
 * operational command.
 */
class ReindexSearchCommand extends Command
{
    protected $signature = 'search:reindex {--store= : Reindex only this store id}';

    protected $description = 'Run a full search reindex for one store or every store';

    public function handle(ReindexStore $reindexStore, TenantContext $tenantContext): int
    {
        $storeId = $this->option('store');

        $stores = $storeId !== null
            ? Store::query()->where('id', $storeId)->get()
            : Store::query()->cursor();

        $count = 0;

        foreach ($stores as $store) {
            $tenantContext->scope($store, function () use ($store, $reindexStore) {
                $reindexStore->full($store);
            });
            $count++;
        }

        $this->info("Reindexed {$count} store(s).");

        return self::SUCCESS;
    }
}
