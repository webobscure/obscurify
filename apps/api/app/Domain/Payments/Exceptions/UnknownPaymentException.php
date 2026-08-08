<?php

namespace App\Domain\Payments\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A webhook arrived for a (provider, external_payment_id) pair that
 * matches no Payment we know about. Rejected, not silently swallowed —
 * see spec section 30 "unknown payment rejected safely": safely means
 * without mutating unrelated state or crashing, not silently accepting.
 */
final class UnknownPaymentException extends RuntimeException
{
    public static function forExternalId(string $provider, string $externalPaymentId): self
    {
        return new self("No payment known for provider \"{$provider}\" external id \"{$externalPaymentId}\".");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'unknown_payment',
        ], 404);
    }
}
