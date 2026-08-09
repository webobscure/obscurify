<?php

namespace App\Domain\Shipping\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Covers both "the provider rejected the rate request" and "the provider
 * timed out" (spec section 15) — the same class of error from the
 * caller's point of view, distinguished only by message. Real providers
 * would throw this for the same reasons (bad credentials, malformed
 * destination, network failure); the fake provider's own dev-only
 * trigger for it lives in FakeShippingProvider, gated behind
 * commerce.shipping.fake.failure_simulation.enabled.
 */
final class ShippingRateCalculationFailedException extends RuntimeException
{
    public static function make(string $reason): self
    {
        return new self("Shipping rate calculation failed: {$reason}");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'shipping_rate_calculation_failed',
        ], 422);
    }
}
