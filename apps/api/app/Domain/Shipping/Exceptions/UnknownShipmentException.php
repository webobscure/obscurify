<?php

namespace App\Domain\Shipping\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A webhook arrived for a (provider, external_shipment_id) pair that
 * matches no Shipment we know about. Rejected, not silently swallowed —
 * mirrors Payments' UnknownPaymentException.
 */
final class UnknownShipmentException extends RuntimeException
{
    public static function forExternalId(string $provider, string $externalShipmentId): self
    {
        return new self("No shipment known for provider \"{$provider}\" external id \"{$externalShipmentId}\".");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'unknown_shipment',
        ], 404);
    }
}
