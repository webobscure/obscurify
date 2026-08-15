<?php

namespace App\Domain\Search\Http\Resources;

use App\Domain\Search\Models\SearchProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SearchProvider
 */
final class SearchProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'is_enabled' => $this->is_enabled,
            'config' => $this->config,
        ];
    }
}
