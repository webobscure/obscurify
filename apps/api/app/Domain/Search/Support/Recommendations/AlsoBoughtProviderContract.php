<?php

namespace App\Domain\Search\Support\Recommendations;

/**
 * Forward-compatibility only — see RelatedProductsProviderContract's
 * docblock; the same applies to every interface in this namespace.
 */
interface AlsoBoughtProviderContract
{
    /**
     * @return list<string> product ids, ordered by relevance
     */
    public function alsoBoughtWith(string $storeId, string $productId, int $limit): array;
}
