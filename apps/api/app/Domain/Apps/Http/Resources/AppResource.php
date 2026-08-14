<?php

namespace App\Domain\Apps\Http\Resources;

use App\Domain\Apps\Models\App;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never includes the OAuth client secret — that is only ever returned
 * once, by AppController::store(), the same discipline as
 * WebhookSubscriptionResource.
 *
 * @mixin App
 */
final class AppResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'type' => $this->type->value,
            'name' => $this->name,
            'slug' => $this->slug,
            'developer' => $this->developer,
            'description' => $this->description,
            'redirect_urls' => $this->redirect_urls,
            'requested_scopes' => $this->requested_scopes,
            'status' => $this->status->value,
            'client_id' => $this->whenLoaded('oauthClient', fn () => $this->oauthClient?->client_id),
            'created_at' => $this->created_at,
        ];
    }
}
