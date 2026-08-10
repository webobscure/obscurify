<?php

namespace App\Domain\Returns\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Spec section 4: a return can never claim more of an OrderItem than is
 * actually returnable — shipped quantity minus whatever is already
 * covered by another active (non-rejected, non-cancelled) ReturnRequest.
 * Checked under a row lock in RequestReturn, the same discipline
 * FulfillmentOvershipmentException enforces one layer earlier.
 */
final class ReturnOverReceiptException extends RuntimeException
{
    public static function exceedsReturnableQuantity(string $orderItemId): self
    {
        return new self("Cannot return more of order item \"{$orderItemId}\" than was shipped and not already returned.");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'return_over_receipt',
        ], 422);
    }
}
