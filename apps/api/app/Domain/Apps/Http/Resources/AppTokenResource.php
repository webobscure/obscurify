<?php

namespace App\Domain\Apps\Http\Resources;

use App\Domain\Apps\Models\AppToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never includes `token_hash` — admin visibility is for audit
 * (created_at/expires_at/revoked_at/rotated_from_id), never the ability
 * to reconstruct or use the token itself.
 *
 * @mixin AppToken
 */
final class AppTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'scope' => $this->scope,
            'rotated_from_id' => $this->rotated_from_id,
            'expires_at' => $this->expires_at,
            'revoked_at' => $this->revoked_at,
            'created_at' => $this->created_at,
        ];
    }
}
