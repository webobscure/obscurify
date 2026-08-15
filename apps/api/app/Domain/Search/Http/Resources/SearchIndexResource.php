<?php

namespace App\Domain\Search\Http\Resources;

use App\Domain\Search\Models\SearchIndex;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SearchIndex
 */
final class SearchIndexResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'document_count' => $this->document_count,
            'last_full_reindex_at' => $this->last_full_reindex_at,
            'last_indexed_at' => $this->last_indexed_at,
            'error_message' => $this->error_message,
        ];
    }
}
