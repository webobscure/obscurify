<?php

namespace App\Domain\Search\Http\Resources;

use App\Domain\Search\Models\SearchSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SearchSettings
 */
final class SearchSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'active_provider_id' => $this->active_provider_id,
            'active_provider' => new SearchProviderResource($this->whenLoaded('activeProvider')),
            'results_per_page' => $this->results_per_page,
            'autocomplete_limit' => $this->autocomplete_limit,
            'typo_tolerance_enabled' => $this->typo_tolerance_enabled,
            'synonyms_enabled' => $this->synonyms_enabled,
            'facets_enabled' => $this->facets_enabled,
        ];
    }
}
