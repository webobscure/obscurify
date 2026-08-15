<?php

namespace App\Domain\Notifications\Http\Resources;

use App\Domain\Notifications\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The list-view shape — omits body_html/recipients/deliveries, which a
 * `GET /notifications` list only needs to let the merchant pick which
 * one to open. See NotificationResource for the full detail shape.
 *
 * @mixin Notification
 */
final class NotificationSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel->value,
            'event_type' => $this->event_type,
            'subject' => $this->subject,
            'triggered_by' => $this->triggered_by->value,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
        ];
    }
}
