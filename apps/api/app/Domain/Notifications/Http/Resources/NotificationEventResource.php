<?php

namespace App\Domain\Notifications\Http\Resources;

use App\Domain\Notifications\Models\NotificationEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationEvent
 */
final class NotificationEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'channel' => $this->channel->value,
            'template_id' => $this->template_id,
            'template' => new NotificationTemplateResource($this->whenLoaded('template')),
            'is_enabled' => $this->is_enabled,
        ];
    }
}
