<?php

namespace App\Domain\Search\Support;

use App\Domain\Search\Models\SearchSynonym;

/**
 * Expands a tokenized query into per-token alternative groups (spec
 * section 9) before it ever reaches a SearchProviderContract — a
 * synonym is a platform-level query-rewrite concern, not something
 * every future provider must reimplement (see ADR-028). Reads
 * SearchSynonym under the caller's own active TenantContext, the same
 * ambient-scope convention every other tenant-scoped read in this
 * codebase relies on.
 *
 * Returns one group per original query word — `["tv"]` expands to
 * `["tv", "television"]` — deliberately a *group of alternatives*
 * (any one of which satisfies that word), not additional words appended
 * to a single flat AND-list: "tv" and "television" never both appear in
 * the same document's text, so requiring both would make the synonym
 * useless. See DatabaseSearchProvider::applyTextMatch() for how a group
 * becomes one OR-clause, with groups still AND-ed against each other.
 */
final class SynonymExpander
{
    public function __construct(private readonly SearchTextNormalizer $normalizer) {}

    /**
     * @param  list<string>  $tokens
     * @return list<list<string>>
     */
    public function expand(array $tokens): array
    {
        if ($tokens === []) {
            return [];
        }

        $synonyms = SearchSynonym::query()->where('is_active', true)->get();

        return array_map(function (string $token) use ($synonyms) {
            $group = [$token];

            foreach ($synonyms as $synonym) {
                $normalizedTerm = $this->normalizer->normalize($synonym->term);
                $normalizedSynonyms = array_map($this->normalizer->normalize(...), $synonym->synonyms);

                if ($normalizedTerm === $token) {
                    array_push($group, ...$normalizedSynonyms);
                } elseif ($synonym->is_bidirectional && in_array($token, $normalizedSynonyms, true)) {
                    $group[] = $normalizedTerm;
                }
            }

            return array_values(array_unique(array_filter($group, fn (string $t) => $t !== '')));
        }, $tokens);
    }
}
