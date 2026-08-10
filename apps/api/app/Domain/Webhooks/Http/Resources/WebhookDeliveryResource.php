<?php

namespace App\Domain\Webhooks\Http\Resources;

use App\Domain\Webhooks\Models\WebhookDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WebhookDelivery
 */
final class WebhookDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'webhook_subscription_id' => $this->webhook_subscription_id,
            'outbox_event_id' => $this->outbox_event_id,
            'event_type' => $this->event_type,
            'status' => $this->status->value,
            'attempt_count' => $this->attempt_count,
            'response_code' => $this->response_code,
            'error_message' => $this->error_message,
            'last_attempted_at' => $this->last_attempted_at,
            'next_retry_at' => $this->next_retry_at,
            'delivered_at' => $this->delivered_at,
            'created_at' => $this->created_at,
        ];
    }
}
