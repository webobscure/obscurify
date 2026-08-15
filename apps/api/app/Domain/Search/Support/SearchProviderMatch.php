<?php

namespace App\Domain\Search\Support;

final readonly class SearchProviderMatch
{
    public function __construct(
        public string $productId,
        public float $score,
    ) {}
}
