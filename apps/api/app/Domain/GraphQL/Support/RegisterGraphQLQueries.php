<?php

namespace App\Domain\GraphQL\Support;

use App\Domain\GraphQL\Queries\AnalyticsQueries;
use App\Domain\GraphQL\Queries\CartQueries;
use App\Domain\GraphQL\Queries\CatalogQueries;
use App\Domain\GraphQL\Queries\CmsQueries;
use App\Domain\GraphQL\Queries\CustomerQueries;
use App\Domain\GraphQL\Queries\NotificationQueries;
use App\Domain\GraphQL\Queries\OrderQueries;
use App\Domain\GraphQL\Queries\SearchQueries;

/**
 * Every built-in top-level Query field (spec section 3), registered
 * once at boot before the schema is built — see SchemaRegistry's
 * docblock for why extensions must register before this runs too.
 */
final class RegisterGraphQLQueries
{
    public function handle(QueryFieldRegistry $queries, TypeRegistry $types): void
    {
        CatalogQueries::register($queries, $types);
        CmsQueries::register($queries, $types);
        SearchQueries::register($queries, $types);
        CartQueries::register($queries, $types);
        CustomerQueries::register($queries, $types);
        OrderQueries::register($queries, $types);
        NotificationQueries::register($queries, $types);
        AnalyticsQueries::register($queries, $types);
    }
}
