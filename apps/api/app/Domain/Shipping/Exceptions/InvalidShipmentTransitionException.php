<?php

namespace App\Domain\Shipping\Exceptions;

use App\Domain\Shipping\Enums\ShipmentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Unlike Payments' InvalidPaymentTransitionException (see the architecture
 * review's TD-37), this one has a render() from day one — its guard() is
 * actually wired into ProcessShippingWebhook/CancelShipment, not dead
 * code, so leaving it to 500 was never an option here.
 */
final class InvalidShipmentTransitionException extends RuntimeException
{
    public static function make(ShipmentStatus $from, ShipmentStatus $to): self
    {
        return new self("Shipment cannot transition from \"{$from->value}\" to \"{$to->value}\".");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'invalid_shipment_transition',
        ], 409);
    }
}
