<?php

namespace App\Domain\Shipping\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class NoShippingMethodsAvailableException extends RuntimeException
{
    public static function make(): self
    {
        return new self('No shipping methods are available for this destination.');
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'no_shipping_methods_available',
        ], 422);
    }
}
