<?php

namespace App\Domain\Shipping\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Covers every "this quote can't be used" case that isn't specifically
 * expiry (see ShippingQuoteExpiredException): wrong checkout, wrong store,
 * or the underlying ShippingMethod no longer active — spec section 12's
 * revalidation checks.
 */
final class ShippingQuoteInvalidException extends RuntimeException
{
    public static function make(string $reason): self
    {
        return new self("Invalid shipping quote: {$reason}");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'invalid_shipping_quote',
        ], 422);
    }
}
