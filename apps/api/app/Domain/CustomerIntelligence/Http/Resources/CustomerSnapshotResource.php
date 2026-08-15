<?php

namespace App\Domain\CustomerIntelligence\Http\Resources;

use App\Domain\CustomerIntelligence\Models\CustomerSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerSnapshot
 */
final class CustomerSnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'metrics' => $this->metrics,
            'captured_at' => $this->captured_at,
        ];
    }
}
