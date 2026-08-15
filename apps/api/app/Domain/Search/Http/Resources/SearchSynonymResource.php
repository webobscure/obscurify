<?php

namespace App\Domain\Search\Http\Resources;

use App\Domain\Search\Models\SearchSynonym;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SearchSynonym
 */
final class SearchSynonymResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'term' => $this->term,
            'synonyms' => $this->synonyms,
            'is_bidirectional' => $this->is_bidirectional,
            'locale' => $this->locale,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
