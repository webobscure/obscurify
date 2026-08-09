<?php

namespace App\Domain\Shipping\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Covers every way a pickup point selection can be wrong (spec section
 * 6): missing when required, not required but supplied, or not one of a
 * fresh provider lookup's own points for this exact context — all the
 * same class of error from the caller's point of view, distinguished only
 * by message.
 */
final class PickupPointInvalidException extends RuntimeException
{
    public static function make(string $reason): self
    {
        return new self("Pickup point selection invalid: {$reason}");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'invalid_pickup_point',
        ], 422);
    }
}
