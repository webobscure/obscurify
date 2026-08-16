<?php

namespace App\Domain\RussianCommerce\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A store's FiscalizationSettings.receipts_required is true (or a
 * caller otherwise asked for a receipt) but no enabled active
 * FiscalizationProvider is configured — a real admin misconfiguration,
 * not a transient failure. Thrown from CreateFiscalReceipt before any
 * FiscalReceipt row is written; a queued RequestFiscalizationJob calling
 * this simply fails and retries/logs like any other job failure until
 * an admin fixes the Fiscalization Settings page.
 */
final class FiscalizationNotConfiguredException extends RuntimeException
{
    public static function forStore(string $storeId): self
    {
        return new self("Store \"{$storeId}\" requires fiscal receipts but has no active fiscalization provider configured.");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'fiscalization_not_configured',
        ], 422);
    }
}
