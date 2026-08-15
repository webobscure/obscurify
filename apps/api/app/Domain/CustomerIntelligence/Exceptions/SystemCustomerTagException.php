<?php

namespace App\Domain\CustomerIntelligence\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when a merchant tries to delete a system tag (first-order/
 * repeat-customer/inactive/vip — see AutoTagCustomer) or manually assign/
 * remove one — those are exclusively RecomputeCustomerMetrics's to
 * manage, since a manual assignment would just be silently reverted (or
 * re-applied) on the next recompute.
 */
final class SystemCustomerTagException extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function cannotDelete(): self
    {
        return new self('This is a system tag and cannot be deleted.');
    }

    public static function cannotAssignManually(): self
    {
        return new self('This is a system tag and is assigned automatically — it cannot be assigned or removed manually.');
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage(), 'error' => 'system_tag'], 422);
    }
}
