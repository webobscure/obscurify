<?php

namespace App\Domain\Customers\Http\Resources;

use App\Shared\Commerce\Models\OutboxEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OutboxEvent
 */
final class CustomerActivityEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'payload' => $this->payload,
            'occurred_at' => $this->occurred_at,
        ];
    }
}
