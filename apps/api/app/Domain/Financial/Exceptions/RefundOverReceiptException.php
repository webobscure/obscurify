<?php

namespace App\Domain\Financial\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Spec section 4: a refund can never claim more of a ReturnItem than is
 * actually refundable — its quantity minus whatever is already covered
 * by another active (non-failed, non-cancelled) Refund. Checked under a
 * row lock in RequestRefund, the same discipline ReturnOverReceiptException
 * enforces one layer earlier.
 */
final class RefundOverReceiptException extends RuntimeException
{
    public static function exceedsReturnedQuantity(string $returnItemId): self
    {
        return new self("Cannot refund more of return item \"{$returnItemId}\" than was returned and not already refunded.");
    }

    public static function exceedsAvailableBalance(): self
    {
        return new self('Refund amount exceeds the payment\'s remaining refundable balance.');
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'refund_over_receipt',
        ], 422);
    }
}
