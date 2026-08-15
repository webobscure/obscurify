<?php

namespace App\Domain\Search\Application;

use App\Domain\Catalog\Models\Product;
use App\Domain\Search\Enums\SearchIndexStatus;
use App\Domain\Search\Models\SearchDocument;
use App\Domain\Search\Models\SearchIndex;
use App\Domain\Stores\Models\Store;

/**
 * Full and partial reindex (spec section 4: "Merchant must also be
 * able to run: Full Reindex, Partial Reindex"). Chunked to bound
 * memory regardless of catalog size (spec section 16: "Chunked full
 * indexing") — never loads every product into memory at once.
 */
final class ReindexStore
{
    private const int CHUNK_SIZE = 100;

    public function __construct(private readonly BuildSearchDocument $buildSearchDocument) {}

    /**
     * Full reindex: rebuilds every product's SearchDocument, then
     * removes any stale document left over from a product that no
     * longer exists (soft-deleted since the last reindex, or whose
     * document exists but the product row itself was hard-deleted).
     */
    public function full(Store $store): SearchIndex
    {
        $index = $this->markBuilding($store);
        $seenProductIds = [];

        Product::query()->withTrashed()->chunkById(self::CHUNK_SIZE, function ($products) use (&$seenProductIds) {
            foreach ($products as $product) {
                $this->buildSearchDocument->handle($product);
                $seenProductIds[] = $product->id;
            }
        });

        SearchDocument::query()->withoutGlobalScopes()
            ->where('store_id', $store->id)
            ->whereNotIn('product_id', $seenProductIds === [] ? ['__none__'] : $seenProductIds)
            ->delete();

        return $this->markReady($index, fullReindex: true);
    }

    /**
     * Partial reindex: rebuilds only the given products — used for a
     * merchant-triggered "reindex these products" action, distinct from
     * the automatic per-event incremental indexing IndexProductJob
     * already does.
     *
     * @param  list<string>  $productIds
     */
    public function partial(Store $store, array $productIds): SearchIndex
    {
        $index = SearchIndex::query()->where('store_id', $store->id)->firstOrFail();

        Product::query()->withTrashed()->whereIn('id', $productIds)->chunkById(self::CHUNK_SIZE, function ($products) {
            foreach ($products as $product) {
                $this->buildSearchDocument->handle($product);
            }
        });

        return $this->markReady($index, fullReindex: false);
    }

    private function markBuilding(Store $store): SearchIndex
    {
        $index = SearchIndex::query()->where('store_id', $store->id)->firstOrFail();
        $index->update(['status' => SearchIndexStatus::Building->value]);

        return $index;
    }

    private function markReady(SearchIndex $index, bool $fullReindex): SearchIndex
    {
        $documentCount = SearchDocument::query()->where('store_id', $index->store_id)->count();

        $index->update([
            'status' => SearchIndexStatus::Ready->value,
            'document_count' => $documentCount,
            'last_indexed_at' => now(),
            'last_full_reindex_at' => $fullReindex ? now() : $index->last_full_reindex_at,
            'error_message' => null,
        ]);

        return $index->fresh();
    }
}
