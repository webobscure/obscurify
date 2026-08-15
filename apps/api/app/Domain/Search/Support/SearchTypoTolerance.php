<?php

namespace App\Domain\Search\Support;

/**
 * "Simple typo tolerance abstraction" (spec section 5) — a single
 * `correct()` seam DatabaseSearchProvider calls when a token matches
 * nothing directly, so a future provider swap (Meilisearch/Typesense
 * both have real built-in typo tolerance) can simply not call this at
 * all rather than needing this abstraction removed. The default
 * strategy is Levenshtein distance against a bounded candidate
 * dictionary — no external service, no ML, genuinely "simple."
 */
final class SearchTypoTolerance
{
    private const int MAX_DISTANCE = 2;

    /**
     * Returns the closest dictionary word within MAX_DISTANCE, or null
     * if nothing is close enough to be worth suggesting — a wrong
     * correction on a genuinely novel search term is worse than no
     * correction at all.
     *
     * @param  list<string>  $dictionary
     */
    public function correct(string $token, array $dictionary): ?string
    {
        if ($token === '' || $dictionary === []) {
            return null;
        }

        $best = null;
        $bestDistance = self::MAX_DISTANCE + 1;

        foreach ($dictionary as $candidate) {
            if ($candidate === $token) {
                return $token;
            }

            // levenshtein() only accepts strings up to 255 bytes and is a
            // fixed-cost C call — cheap enough to run over a bounded
            // dictionary per zero-result token, not per document.
            $distance = levenshtein($token, $candidate);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $candidate;
            }
        }

        return $bestDistance <= self::MAX_DISTANCE ? $best : null;
    }
}
