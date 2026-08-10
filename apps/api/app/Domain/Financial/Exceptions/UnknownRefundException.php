<?php

namespace App\Domain\Financial\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A refund webhook arrived for a (provider, external_refund_id) pair
 * that matches no Refund we know about — mirrors UnknownPaymentException.
 * Rejected, not silently swallowed.
 */
final class UnknownRefundException extends RuntimeException
{
    public static function forExternalId(string $provider, string $externalRefundId): self
    {
        return new self("No refund known for provider \"{$provider}\" external id \"{$externalRefundId}\".");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'unknown_refund',
        ], 404);
    }
}
