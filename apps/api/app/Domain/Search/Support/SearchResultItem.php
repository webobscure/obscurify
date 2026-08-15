<?php

namespace App\Domain\Search\Support;

final readonly class SearchResultItem
{
    public function __construct(
        public string $productId,
        public string $title,
        public string $slug,
        public ?string $description,
        public ?string $vendor,
        public ?string $productType,
        public ?int $priceMin,
        public ?int $priceMax,
        public ?string $currency,
        public ?string $thumbnailUrl,
        public bool $availability,
        public float $score,
        public bool $isPinned,
    ) {}
}
