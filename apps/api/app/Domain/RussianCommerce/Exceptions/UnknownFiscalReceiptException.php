<?php

namespace App\Domain\RussianCommerce\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A fiscalization callback arrived for a (provider, external_receipt_id)
 * pair that matches no FiscalReceipt we know about — mirrors Payments'
 * UnknownPaymentException. Rejected safely, not silently swallowed.
 */
final class UnknownFiscalReceiptException extends RuntimeException
{
    public static function forExternalId(string $provider, string $externalReceiptId): self
    {
        return new self("No fiscal receipt known for provider \"{$provider}\" external id \"{$externalReceiptId}\".");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'unknown_fiscal_receipt',
        ], 404);
    }
}
