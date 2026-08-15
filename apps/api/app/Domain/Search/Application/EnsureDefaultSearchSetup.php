<?php

namespace App\Domain\Search\Application;

use App\Domain\Search\Enums\SearchIndexStatus;
use App\Domain\Search\Models\SearchIndex;
use App\Domain\Search\Models\SearchProvider;
use App\Domain\Search\Models\SearchSettings;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\TenantContext;

/**
 * Idempotently seeds a store's default search setup: one "database"
 * SearchProvider, one SearchSettings row pointing at it, and one
 * SearchIndex tracking row — so DatabaseSearchProvider genuinely works
 * out of the box (spec: "The default implementation must be
 * DatabaseSearchProvider") without a merchant configuring anything
 * first. Called both from `search:install` and lazily by the admin
 * Search Dashboard/Settings endpoints, the same "auto-create on first
 * read" convention Milestones 20/21 established.
 */
final class EnsureDefaultSearchSetup
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Store $store): void
    {
        $this->tenantContext->scope($store, function () use ($store) {
            $provider = SearchProvider::query()->firstOrCreate(
                ['store_id' => $store->id, 'code' => SearchProvider::DATABASE],
                ['name' => 'Database Search', 'is_enabled' => true, 'config' => []],
            );

            SearchSettings::query()->firstOrCreate(
                ['store_id' => $store->id],
                ['active_provider_id' => $provider->id],
            );

            SearchIndex::query()->firstOrCreate(
                ['store_id' => $store->id],
                ['status' => SearchIndexStatus::Building->value, 'document_count' => 0],
            );
        });
    }
}
