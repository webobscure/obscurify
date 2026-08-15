<?php

namespace App\Domain\Search\Support\Recommendations;

/**
 * Forward-compatibility only — see RelatedProductsProviderContract's
 * docblock; the same applies to every interface in this namespace.
 */
interface FrequentlyBoughtTogetherProviderContract
{
    /**
     * @return list<string> product ids, ordered by co-purchase strength
     */
    public function frequentlyBoughtWith(string $storeId, string $productId, int $limit): array;
}
