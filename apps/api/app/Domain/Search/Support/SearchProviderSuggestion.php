<?php

namespace App\Domain\Search\Support;

final readonly class SearchProviderSuggestion
{
    public function __construct(
        public string $productId,
        public string $title,
        public ?string $thumbnailUrl,
    ) {}
}
