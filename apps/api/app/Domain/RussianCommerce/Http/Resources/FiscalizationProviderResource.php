<?php

namespace App\Domain\RussianCommerce\Http\Resources;

use App\Domain\RussianCommerce\Models\FiscalizationProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `credentials` is never serialized (spec section 20: "do not log
 * provider secrets" applies just as much to API responses) — only
 * whether one is currently set, so the admin UI can show "configured" /
 * "not configured" without ever round-tripping the encrypted value.
 *
 * @mixin FiscalizationProvider
 */
final class FiscalizationProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'is_enabled' => $this->is_enabled,
            'config' => $this->config,
            'has_credentials' => $this->credentials !== null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
