<?php

namespace App\Domain\Search\Application;

use App\Domain\Search\Models\SearchRule;
use App\Domain\Search\Support\SearchTextNormalizer;

final class UpdateSearchRule
{
    public function __construct(private readonly SearchTextNormalizer $normalizer) {}

    /**
     * @param  array{name?: string, keyword?: ?string, action?: string, product_id?: string, boost_amount?: ?int, is_active?: bool, position?: int}  $data
     */
    public function handle(SearchRule $rule, array $data): SearchRule
    {
        $rule->fill([
            'name' => $data['name'] ?? $rule->name,
            'keyword' => array_key_exists('keyword', $data) ? ($data['keyword'] !== null ? $this->normalizer->normalize($data['keyword']) : null) : $rule->keyword,
            'action' => $data['action'] ?? $rule->action->value,
            'product_id' => $data['product_id'] ?? $rule->product_id,
            'boost_amount' => array_key_exists('boost_amount', $data) ? $data['boost_amount'] : $rule->boost_amount,
            'is_active' => $data['is_active'] ?? $rule->is_active,
            'position' => $data['position'] ?? $rule->position,
        ])->save();

        return $rule->fresh();
    }
}
