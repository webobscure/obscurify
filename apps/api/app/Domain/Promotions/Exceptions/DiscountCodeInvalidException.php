<?php

namespace App\Domain\Promotions\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Covers every "this code can't be applied" case: unknown code, inactive,
 * expired, usage limit exhausted, or the cart doesn't meet the linked
 * Promotion's rules — all the same class of error from the caller's point
 * of view, distinguished only by message.
 */
final class DiscountCodeInvalidException extends RuntimeException
{
    public static function make(string $reason): self
    {
        return new self("Invalid discount code: {$reason}");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'invalid_discount_code',
        ], 422);
    }
}
