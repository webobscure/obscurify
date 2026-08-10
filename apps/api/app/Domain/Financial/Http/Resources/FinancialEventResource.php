<?php

namespace App\Domain\Financial\Http\Resources;

use App\Domain\Financial\Models\FinancialEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FinancialEvent
 */
final class FinancialEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'description' => $this->description,
            'occurred_at' => $this->occurred_at,
        ];
    }
}
