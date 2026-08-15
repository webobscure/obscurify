<?php

namespace App\Domain\Search\Application;

use App\Domain\Search\Models\PinnedSearchResult;
use App\Domain\Search\Support\SearchTextNormalizer;

final class CreatePinnedSearchResult
{
    public function __construct(private readonly SearchTextNormalizer $normalizer) {}

    /**
     * @param  array{keyword: string, product_id: string, position?: int, is_active?: bool}  $data
     */
    public function handle(array $data): PinnedSearchResult
    {
        return PinnedSearchResult::query()->create([
            'keyword' => $this->normalizer->normalize($data['keyword']),
            'product_id' => $data['product_id'],
            'position' => $data['position'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
