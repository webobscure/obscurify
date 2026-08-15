<?php

namespace App\Domain\Search\Support;

use App\Domain\Catalog\Models\Category;
use App\Domain\Collections\Models\Collection;

/**
 * Turns a provider's raw facet counts (id-keyed for category/collection,
 * "Option:Value"-keyed for variant options) into a response shape a
 * storefront can render directly — resolving category/collection
 * *labels* live from their own tables at this one read point, never
 * stored redundantly on SearchDocument (see that model's own docblock
 * for why: a renamed collection would otherwise need a reindex to stay
 * correct everywhere).
 */
final class SearchFacetBuilder
{
    /**
     * @param  array<string, array<string, int>|array{min: int|null, max: int|null}>  $rawFacets
     * @return array<string, mixed>
     */
    public function build(string $storeId, array $rawFacets): array
    {
        return [
            'vendor' => $this->valueCounts($rawFacets['vendor'] ?? []),
            'product_type' => $this->valueCounts($rawFacets['product_type'] ?? []),
            'availability' => $this->valueCounts($rawFacets['availability'] ?? []),
            'tags' => $this->valueCounts($rawFacets['tags'] ?? []),
            'category' => $this->labeledCounts($storeId, $rawFacets['category'] ?? [], Category::class),
            'collection' => $this->labeledCounts($storeId, $rawFacets['collection'] ?? [], Collection::class),
            'variant_options' => $this->variantOptionCounts($rawFacets['variant_options'] ?? []),
            'price' => [
                'min' => $rawFacets['price']['min'] ?? null,
                'max' => $rawFacets['price']['max'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{value: string, count: int}>
     */
    private function valueCounts(array $counts): array
    {
        $result = [];

        foreach ($counts as $value => $count) {
            $result[] = ['value' => (string) $value, 'count' => $count];
        }

        usort($result, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $result;
    }

    /**
     * @param  array<string, int>  $counts
     * @param  class-string<Category|Collection>  $modelClass
     * @return list<array{id: string, label: string, count: int}>
     */
    private function labeledCounts(string $storeId, array $counts, string $modelClass): array
    {
        if ($counts === []) {
            return [];
        }

        $ids = array_keys($counts);
        $titles = $modelClass::query()->withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->whereIn('id', $ids)
            ->pluck('title', 'id');

        $result = [];

        foreach ($counts as $id => $count) {
            $title = $titles[$id] ?? null;

            if ($title === null) {
                continue;
            }

            $result[] = ['id' => $id, 'label' => $title, 'count' => $count];
        }

        usort($result, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $result;
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{option: string, value: string, count: int}>
     */
    private function variantOptionCounts(array $counts): array
    {
        $result = [];

        foreach ($counts as $key => $count) {
            [$option, $value] = array_pad(explode(':', $key, 2), 2, '');
            $result[] = ['option' => $option, 'value' => $value, 'count' => $count];
        }

        usort($result, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $result;
    }
}
