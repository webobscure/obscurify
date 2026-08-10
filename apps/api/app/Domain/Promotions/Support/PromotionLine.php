<?php

namespace App\Domain\Promotions\Support;

/**
 * One cart line's promotion-relevant facts — collection/category
 * membership is resolved once up front (see BuildPromotionContext) so
 * rule/action evaluation never queries the database per-line.
 */
final readonly class PromotionLine
{
    /**
     * @param  string[]  $collectionIds
     * @param  string[]  $categoryIds
     */
    public function __construct(
        public string $productId,
        public string $productVariantId,
        public int $quantity,
        public int $unitPrice,
        public array $collectionIds,
        public array $categoryIds,
    ) {}

    public function lineTotal(): int
    {
        return $this->quantity * $this->unitPrice;
    }
}
