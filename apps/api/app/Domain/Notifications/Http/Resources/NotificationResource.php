<?php

namespace App\Domain\Notifications\Http\Resources;

use App\Domain\Notifications\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The full detail shape — includes recipients and deliveries, used by
 * the admin Notification Center's detail view and Delivery Log. See
 * NotificationSummaryResource for the list-view shape.
 *
 * @mixin Notification
 */
final class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'template_id' => $this->template_id,
            'channel' => $this->channel->value,
            'event_type' => $this->event_type,
            'subject' => $this->subject,
            'body_text' => $this->body_text,
            'body_html' => $this->body_html,
            'related_type' => $this->related_type,
            'related_id' => $this->related_id,
            'workflow_execution_id' => $this->workflow_execution_id,
            'triggered_by' => $this->triggered_by->value,
            'status' => $this->status->value,
            'recipients' => NotificationRecipientResource::collection($this->whenLoaded('recipients')),
            'deliveries' => NotificationDeliveryResource::collection($this->whenLoaded('deliveries')),
            'created_at' => $this->created_at,
        ];
    }
}
