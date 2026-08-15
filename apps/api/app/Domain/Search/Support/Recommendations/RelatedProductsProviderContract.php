<?php

namespace App\Domain\Search\Support\Recommendations;

/**
 * Forward-compatibility only (spec section 13: "Do NOT implement
 * recommendations. Only create interfaces for future"). No
 * implementation exists this milestone and none is registered anywhere
 * — a future recommendations milestone implements this against
 * SearchDocument (never Product directly), the same provider-neutral
 * discipline the rest of this domain follows.
 */
interface RelatedProductsProviderContract
{
    /**
     * @return list<string> product ids, ordered by relevance
     */
    public function relatedTo(string $storeId, string $productId, int $limit): array;
}
