<?php

namespace App\Domain\Fulfillment\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Spec section 13: fulfilled quantity must never exceed ordered quantity
 * (checked when a Fulfillment is created, against OrderItem, under a row
 * lock) nor reserved quantity (checked at allocation time, against
 * InventoryReservation, under a row lock) — both raise this exception,
 * distinguished by message, since both are the same class of error from
 * the caller's point of view.
 */
final class FulfillmentOvershipmentException extends RuntimeException
{
    public static function exceedsOrderedQuantity(string $orderItemId): self
    {
        return new self("Cannot fulfill more of order item \"{$orderItemId}\" than was ordered.");
    }

    public static function exceedsReservedQuantity(string $orderItemId): self
    {
        return new self("Cannot allocate more of order item \"{$orderItemId}\" than is reserved.");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'fulfillment_overshipment',
        ], 422);
    }
}
