<?php

namespace App\Domain\Payments\Http\Resources;

use App\Domain\Payments\Models\PaymentAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deliberately excludes `metadata` — spec section 25/6 say not to expose
 * raw provider payloads/secrets unnecessarily; the safe summary fields
 * are enough for the admin "Attempts" view.
 *
 * @mixin PaymentAttempt
 */
final class PaymentAttemptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'external_attempt_id' => $this->external_attempt_id,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at,
        ];
    }
}
