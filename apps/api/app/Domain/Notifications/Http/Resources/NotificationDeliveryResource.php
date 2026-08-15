<?php

namespace App\Domain\Notifications\Http\Resources;

use App\Domain\Notifications\Models\NotificationDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationDelivery
 */
final class NotificationDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'notification_id' => $this->notification_id,
            'recipient_id' => $this->recipient_id,
            'channel' => $this->channel->value,
            'provider_id' => $this->provider_id,
            'status' => $this->status->value,
            'attempt_count' => $this->attempt_count,
            'last_attempted_at' => $this->last_attempted_at,
            'next_retry_at' => $this->next_retry_at,
            'delivered_at' => $this->delivered_at,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at,
        ];
    }
}
