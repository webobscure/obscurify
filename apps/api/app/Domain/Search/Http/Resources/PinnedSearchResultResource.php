<?php

namespace App\Domain\Search\Http\Resources;

use App\Domain\Search\Models\PinnedSearchResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PinnedSearchResult
 */
final class PinnedSearchResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'keyword' => $this->keyword,
            'product_id' => $this->product_id,
            'position' => $this->position,
            'is_active' => $this->is_active,
        ];
    }
}
