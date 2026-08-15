<?php

namespace App\Domain\Search\Enums;

/**
 * Downstream-of-a-search actions only — a zero-result search is
 * already fully captured by `SearchQuery.result_count = 0` (see that
 * model's docblock), so it has no separate event row here; adding one
 * would just restate data the query log already owns.
 */
enum SearchAnalyticsEventType: string
{
    case ResultClicked = 'result_clicked';
    case Converted = 'converted';
}
