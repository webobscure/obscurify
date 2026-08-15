<?php

namespace App\Domain\Search\Application;

use App\Domain\Search\Models\PinnedSearchResult;
use App\Domain\Search\Support\SearchTextNormalizer;

final class UpdatePinnedSearchResult
{
    public function __construct(private readonly SearchTextNormalizer $normalizer) {}

    /**
     * @param  array{keyword?: string, product_id?: string, position?: int, is_active?: bool}  $data
     */
    public function handle(PinnedSearchResult $pin, array $data): PinnedSearchResult
    {
        $pin->fill([
            'keyword' => isset($data['keyword']) ? $this->normalizer->normalize($data['keyword']) : $pin->keyword,
            'product_id' => $data['product_id'] ?? $pin->product_id,
            'position' => $data['position'] ?? $pin->position,
            'is_active' => $data['is_active'] ?? $pin->is_active,
        ])->save();

        return $pin->fresh();
    }
}
