<?php

namespace App\Domain\Shipping\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class ShipmentCreationFailedException extends RuntimeException
{
    public static function make(string $reason): self
    {
        return new self("Shipment creation failed: {$reason}");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'shipment_creation_failed',
        ], 422);
    }
}
