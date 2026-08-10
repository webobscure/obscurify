<?php

namespace App\Domain\Financial\Http\Resources;

use App\Domain\Financial\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LedgerEntry
 */
final class LedgerEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account' => $this->account->value,
            'direction' => $this->direction->value,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'created_at' => $this->created_at,
        ];
    }
}
