<?php

namespace App\Domain\Search\Support\Recommendations;

/**
 * Forward-compatibility only — see RelatedProductsProviderContract's
 * docblock; the same applies to every interface in this namespace.
 */
interface TrendingProductsProviderContract
{
    /**
     * @return list<string> product ids, most trending first
     */
    public function trending(string $storeId, int $limit): array;
}
