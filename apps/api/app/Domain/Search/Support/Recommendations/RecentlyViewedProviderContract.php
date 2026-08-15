<?php

namespace App\Domain\Search\Support\Recommendations;

/**
 * Forward-compatibility only — see RelatedProductsProviderContract's
 * docblock; the same applies to every interface in this namespace.
 */
interface RecentlyViewedProviderContract
{
    /**
     * @return list<string> product ids, most recently viewed first
     */
    public function recentlyViewedBy(string $storeId, ?string $customerId, ?string $sessionId, int $limit): array;
}
