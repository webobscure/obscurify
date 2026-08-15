<?php

namespace App\Domain\Notifications\Http\Resources;

use App\Domain\Notifications\Models\NotificationRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationRecipient
 */
final class NotificationRecipientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'notification_id' => $this->notification_id,
            'recipient_type' => $this->recipient_type->value,
            'customer_id' => $this->customer_id,
            'address' => $this->address,
            'read_at' => $this->read_at,
            'notification' => new NotificationSummaryResource($this->whenLoaded('notification')),
        ];
    }
}
