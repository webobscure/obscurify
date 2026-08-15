<?php

namespace App\Domain\Search\Support;

use App\Domain\Search\Models\SearchDocument;
use Illuminate\Support\Carbon;

/**
 * Combines every ranking factor spec section 11 lists — text relevance
 * and manual boost arrive already summed into `$baseScore` (the
 * provider's own relevance score plus any SearchRule boost, applied
 * before this is called — see ExecuteSearch); everything else
 * (popularity, sales, availability, freshness) is read straight off
 * the SearchDocument. Pinned position is handled entirely outside this
 * class — a pin always sorts before every ranked result, not as a
 * score contribution (see ExecuteSearch), so adding it here would
 * double up two different "always win" mechanisms.
 *
 * The weights below are the one place to touch when adding a future
 * ranking factor (spec: "Architecture must allow adding future ranking
 * factors") — each factor is an independent additive term, not a
 * chained multiplier, so a new one never changes how the existing ones
 * behave.
 */
final class SearchRankingEngine
{
    private const float POPULARITY_WEIGHT = 0.01;

    private const float SALES_WEIGHT = 0.05;

    private const float AVAILABILITY_BONUS = 20.0;

    private const float FRESHNESS_MAX_BONUS = 10.0;

    private const int FRESHNESS_WINDOW_DAYS = 30;

    public function score(float $baseScore, SearchDocument $document): float
    {
        return $baseScore
            + $document->popularity * self::POPULARITY_WEIGHT
            + $document->sales_count * self::SALES_WEIGHT
            + ($document->availability ? self::AVAILABILITY_BONUS : 0.0)
            + $this->freshnessBonus($document->product_created_at);
    }

    private function freshnessBonus(Carbon $createdAt): float
    {
        $daysOld = $createdAt->diffInDays(now());

        if ($daysOld >= self::FRESHNESS_WINDOW_DAYS) {
            return 0.0;
        }

        return self::FRESHNESS_MAX_BONUS * (1 - $daysOld / self::FRESHNESS_WINDOW_DAYS);
    }
}
