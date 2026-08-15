<?php

namespace App\Domain\Search\Http\Controllers;

use App\Domain\Search\Application\EnsureDefaultSearchSetup;
use App\Domain\Search\Application\ReindexStore;
use App\Domain\Search\Http\Resources\SearchIndexResource;
use App\Domain\Search\Models\SearchIndex;
use App\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\Request;

/**
 * Backs the admin "Reindex" page (spec section 15) and the Search
 * Dashboard's index status card. Deliberately admin-only, not part of
 * the public storefront surface — spec section 14 lists
 * `POST /search/reindex` under "Storefront API", but an unauthenticated
 * reindex trigger is a real operational/DoS risk with no legitimate
 * customer-facing use; see docs/adr/028-search-platform.md.
 */
final class SearchIndexController extends Controller
{
    public function show(TenantContext $tenantContext, EnsureDefaultSearchSetup $ensureDefaults): SearchIndexResource
    {
        $ensureDefaults->handle($tenantContext->store());

        $index = SearchIndex::query()->where('store_id', $tenantContext->storeId())->firstOrFail();

        return new SearchIndexResource($index);
    }

    /**
     * Full reindex when no `product_ids` are given (spec: "Full
     * Reindex"), partial when they are (spec: "Partial Reindex").
     */
    public function reindex(Request $request, TenantContext $tenantContext, ReindexStore $reindexStore): SearchIndexResource
    {
        $productIds = $request->input('product_ids');
        $store = $tenantContext->store();

        $index = is_array($productIds) && $productIds !== []
            ? $reindexStore->partial($store, $productIds)
            : $reindexStore->full($store);

        return new SearchIndexResource($index);
    }
}
