<?php

namespace App\Domain\Financial\Http\Resources;

use App\Domain\Financial\Models\LedgerTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LedgerTransaction
 */
final class LedgerTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_type' => class_basename($this->reference_type),
            'reference_id' => $this->reference_id,
            'description' => $this->description,
            'entries' => LedgerEntryResource::collection($this->whenLoaded('entries')),
            'occurred_at' => $this->occurred_at,
        ];
    }
}
