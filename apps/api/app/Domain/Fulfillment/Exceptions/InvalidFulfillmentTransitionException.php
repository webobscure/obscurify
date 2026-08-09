<?php

namespace App\Domain\Fulfillment\Exceptions;

use App\Domain\Fulfillment\Enums\FulfillmentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class InvalidFulfillmentTransitionException extends RuntimeException
{
    public static function make(FulfillmentStatus $from, FulfillmentStatus $to): self
    {
        return new self("Fulfillment cannot transition from \"{$from->value}\" to \"{$to->value}\".");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'invalid_fulfillment_transition',
        ], 409);
    }
}
