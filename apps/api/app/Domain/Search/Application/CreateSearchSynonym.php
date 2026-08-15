<?php

namespace App\Domain\Search\Application;

use App\Domain\Search\Models\SearchSynonym;

final class CreateSearchSynonym
{
    /**
     * @param  array{term: string, synonyms: list<string>, is_bidirectional?: bool, locale?: ?string, is_active?: bool}  $data
     */
    public function handle(array $data): SearchSynonym
    {
        return SearchSynonym::query()->create([
            'term' => $data['term'],
            'synonyms' => $data['synonyms'],
            'is_bidirectional' => $data['is_bidirectional'] ?? false,
            'locale' => $data['locale'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
