<?php

namespace App\Domain\Webhooks\Http\Resources;

use App\Domain\Webhooks\Models\WebhookSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never includes `secret` — it is only ever returned once, directly by
 * WebhookSubscriptionController::store(), never re-derivable from this
 * resource afterward (spec: "Never expose secrets in API responses").
 *
 * @mixin WebhookSubscription
 */
final class WebhookSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_type' => $this->owner_type,
            'owner_id' => $this->owner_id,
            'name' => $this->name,
            'target_url' => $this->target_url,
            'event_types' => $this->event_types,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
