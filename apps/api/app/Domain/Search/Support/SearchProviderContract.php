<?php

namespace App\Domain\Search\Support;

use App\Domain\Search\Models\SearchDocument;

/**
 * The one boundary every part of this platform depends on (spec
 * section 2: "The entire platform must depend only on the
 * SearchProviderContract") — mirrors PaymentProviderContract/
 * NotificationProviderContract's own "only the operations every real
 * provider needs" discipline. `DatabaseSearchProvider` is the only
 * implementation this milestone; a future Meilisearch/Typesense/
 * OpenSearch/Elasticsearch provider implements this same interface
 * with no change to ExecuteSearch, SearchFacetBuilder,
 * SearchSuggestionEngine, or any HTTP controller — see
 * docs/architecture/search.md §2.
 */
interface SearchProviderContract
{
    /**
     * Registry key, e.g. "database", "meilisearch".
     */
    public function code(): string;

    public function index(SearchDocument $document): void;

    /**
     * Bulk variant of index() — a real remote provider batches network
     * calls here instead of one round trip per document (spec section
     * 16: "Chunked full indexing").
     *
     * @param  iterable<SearchDocument>  $documents
     */
    public function bulkIndex(iterable $documents): void;

    public function delete(string $storeId, string $productId): void;

    public function search(SearchProviderQuery $query): SearchProviderResult;

    /**
     * @return list<SearchProviderSuggestion>
     */
    public function suggestProducts(string $storeId, string $prefix, int $limit): array;
}
