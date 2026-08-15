<?php

namespace App\Domain\Search\Application;

use App\Domain\Search\Models\SearchSettings;

final class UpdateSearchSettings
{
    /**
     * @param  array{active_provider_id?: ?string, results_per_page?: int, autocomplete_limit?: int, typo_tolerance_enabled?: bool, synonyms_enabled?: bool, facets_enabled?: bool}  $data
     */
    public function handle(SearchSettings $settings, array $data): SearchSettings
    {
        $settings->fill([
            'active_provider_id' => array_key_exists('active_provider_id', $data) ? $data['active_provider_id'] : $settings->active_provider_id,
            'results_per_page' => $data['results_per_page'] ?? $settings->results_per_page,
            'autocomplete_limit' => $data['autocomplete_limit'] ?? $settings->autocomplete_limit,
            'typo_tolerance_enabled' => $data['typo_tolerance_enabled'] ?? $settings->typo_tolerance_enabled,
            'synonyms_enabled' => $data['synonyms_enabled'] ?? $settings->synonyms_enabled,
            'facets_enabled' => $data['facets_enabled'] ?? $settings->facets_enabled,
        ])->save();

        return $settings->fresh();
    }
}
