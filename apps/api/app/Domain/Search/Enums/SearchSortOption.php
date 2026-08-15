<?php

namespace App\Domain\Search\Enums;

/**
 * Spec section 7. `MostViewed` is registered for forward-compatibility
 * only — no page-view tracking exists anywhere in this platform yet
 * (spec: "Most viewed (future-ready)"), matching the same
 * catalog-only-not-wired-up convention used elsewhere (e.g. M19's
 * `OrderCancelled`).
 */
enum SearchSortOption: string
{
    case Relevance = 'relevance';
    case Newest = 'newest';
    case Oldest = 'oldest';
    case PriceAsc = 'price_asc';
    case PriceDesc = 'price_desc';
    case BestSelling = 'best_selling';
    case MostViewed = 'most_viewed';
    case Manual = 'manual';
}
