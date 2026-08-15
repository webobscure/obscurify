<?php

namespace App\Domain\Search\Application;

use App\Domain\Search\Models\SearchSynonym;

final class UpdateSearchSynonym
{
    /**
     * @param  array{term?: string, synonyms?: list<string>, is_bidirectional?: bool, locale?: ?string, is_active?: bool}  $data
     */
    public function handle(SearchSynonym $synonym, array $data): SearchSynonym
    {
        $synonym->fill([
            'term' => $data['term'] ?? $synonym->term,
            'synonyms' => $data['synonyms'] ?? $synonym->synonyms,
            'is_bidirectional' => $data['is_bidirectional'] ?? $synonym->is_bidirectional,
            'locale' => array_key_exists('locale', $data) ? $data['locale'] : $synonym->locale,
            'is_active' => $data['is_active'] ?? $synonym->is_active,
        ])->save();

        return $synonym->fresh();
    }
}
