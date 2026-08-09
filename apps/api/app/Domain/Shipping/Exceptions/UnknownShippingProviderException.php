<?php

namespace App\Domain\Shipping\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 404, not 422 like its Payments equivalent — the only place this is
 * reachable from client input is the webhook URL path
 * (shipping/webhooks/{provider}), where an unknown provider is a
 * nonexistent route resource, not a validation failure. (Internal
 * resolution of a ShippingMethod's own provider column never throws this
 * — see CalculateShippingRates, which treats an unregistered provider as
 * "this method currently offers nothing", not an error.)
 */
final class UnknownShippingProviderException extends RuntimeException
{
    public static function forCode(string $code): self
    {
        return new self("Unknown shipping provider \"{$code}\".");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Not found.',
            'error' => 'not_found',
        ], 404);
    }
}
