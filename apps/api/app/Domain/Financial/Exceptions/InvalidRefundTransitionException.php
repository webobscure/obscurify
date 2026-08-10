<?php

namespace App\Domain\Financial\Exceptions;

use App\Domain\Financial\Enums\RefundStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class InvalidRefundTransitionException extends RuntimeException
{
    public static function make(RefundStatus $from, RefundStatus $to): self
    {
        return new self("Refund cannot transition from \"{$from->value}\" to \"{$to->value}\".");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'invalid_refund_transition',
        ], 409);
    }
}
