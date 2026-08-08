<?php

namespace App\Domain\Storefront\Http\Resources;

use App\Domain\Payments\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Storefront-safe payment representation — no store_id/order internals
 * beyond what the visitor needs to complete or check on their own
 * payment. Callers must eager-load 'sessions' before rendering (see
 * StorefrontPaymentController) — the redirect_url lookup below assumes
 * it's already in memory.
 *
 * @mixin Payment
 */
final class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $session = $this->sessions->last();

        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'status' => $this->status->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'redirect_url' => $session?->redirect_url,
        ];
    }
}
