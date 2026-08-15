<?php

namespace App\Domain\Customers\Http\Resources;

use App\Domain\Customers\Models\CustomerSession;
use App\Domain\Customers\Support\CurrentCustomerContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * No token hashes here, only device metadata — what a "your active
 * sessions" screen needs to let a customer recognize and revoke a
 * session, never anything that could itself be replayed as a credential.
 *
 * @mixin CustomerSession
 */
final class CustomerSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'is_current' => app(CurrentCustomerContext::class)->session()->id === $this->id,
            'last_used_at' => $this->last_used_at,
            'created_at' => $this->created_at,
        ];
    }
}
