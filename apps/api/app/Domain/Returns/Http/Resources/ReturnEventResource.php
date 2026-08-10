<?php

namespace App\Domain\Returns\Http\Resources;

use App\Domain\Returns\Models\ReturnEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReturnEvent
 */
final class ReturnEventResource extends JsonResource
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
