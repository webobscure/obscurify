<?php

namespace App\Domain\Shipping\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class ShippingQuoteExpiredException extends RuntimeException
{
    public static function make(): self
    {
        return new self('This shipping quote has expired.');
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'shipping_quote_expired',
        ], 409);
    }
}
