<?php

namespace App\Console\Commands;

use App\Domain\Search\Application\RefreshSearchSuggestions;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Not wired into Laravel's scheduler — run externally on a cron,
 * matching every other operational command in this codebase
 * (`outbox:process`, `webhooks:retry-failed`, ...).
 */
class RefreshSearchSuggestionsCommand extends Command
{
    protected $signature = 'search:refresh-suggestions {--store= : Refresh only this store id}';

    protected $description = 'Rebuild the popular-queries autocomplete cache from recent SearchQuery rows';

    public function handle(RefreshSearchSuggestions $refreshSearchSuggestions, TenantContext $tenantContext): int
    {
        $storeId = $this->option('store');

        $stores = $storeId !== null
            ? Store::query()->where('id', $storeId)->get()
            : Store::query()->cursor();

        $count = 0;

        foreach ($stores as $store) {
            $tenantContext->scope($store, function () use ($store, $refreshSearchSuggestions) {
                $refreshSearchSuggestions->handle($store);
            });
            $count++;
        }

        $this->info("Refreshed search suggestions for {$count} store(s).");

        return self::SUCCESS;
    }
}
