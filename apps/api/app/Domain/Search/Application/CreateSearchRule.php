<?php

namespace App\Domain\Search\Application;

use App\Domain\Search\Models\SearchRule;
use App\Domain\Search\Support\SearchTextNormalizer;

final class CreateSearchRule
{
    public function __construct(private readonly SearchTextNormalizer $normalizer) {}

    /**
     * @param  array{name: string, keyword?: ?string, action: string, product_id: string, boost_amount?: ?int, is_active?: bool, position?: int}  $data
     */
    public function handle(array $data): SearchRule
    {
        return SearchRule::query()->create([
            'name' => $data['name'],
            'keyword' => isset($data['keyword']) ? $this->normalizer->normalize($data['keyword']) : null,
            'action' => $data['action'],
            'product_id' => $data['product_id'],
            'boost_amount' => $data['boost_amount'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'position' => $data['position'] ?? 0,
        ]);
    }
}
