<?php

namespace App\Domain\Search\Http\Resources;

use App\Domain\Search\Support\SearchResult;
use App\Domain\Search\Support\SearchResultItem;

/**
 * Not a JsonResource — SearchResult is a plain value object (see
 * ExecuteSearch), not an Eloquent model. Shared by the storefront
 * search controller and the admin "preview a search" endpoint so both
 * surfaces render the identical provider-neutral shape (spec section
 * 14: "Responses must be provider-independent").
 */
final class SearchResultResource
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(SearchResult $result): array
    {
        return [
            'data' => array_map(self::item(...), $result->items),
            'meta' => [
                'total' => $result->total,
                'page' => $result->page,
                'per_page' => $result->perPage,
                'search_query_id' => $result->searchQuery->id,
            ],
            'facets' => $result->facets,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function item(SearchResultItem $item): array
    {
        return [
            'product_id' => $item->productId,
            'title' => $item->title,
            'slug' => $item->slug,
            'description' => $item->description,
            'vendor' => $item->vendor,
            'product_type' => $item->productType,
            'price' => [
                'min' => $item->priceMin,
                'max' => $item->priceMax,
                'currency' => $item->currency,
            ],
            'thumbnail_url' => $item->thumbnailUrl,
            'availability' => $item->availability,
            'score' => $item->score,
            'is_pinned' => $item->isPinned,
        ];
    }
}
