<?php

namespace App\Domain\Notifications\Http\Resources;

use App\Domain\Notifications\Models\NotificationChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationChannel
 */
final class NotificationChannelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel->value,
            'provider_id' => $this->provider_id,
            'provider' => new NotificationProviderResource($this->whenLoaded('provider')),
            'is_enabled' => $this->is_enabled,
        ];
    }
}
