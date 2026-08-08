<?php

namespace App\Shared\Commerce\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Same Idempotency-Key reused with a materially different request body.
 */
final class IdempotencyConflictException extends RuntimeException
{
    public static function make(): self
    {
        return new self('This Idempotency-Key was already used with a different request.');
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'idempotency_key_conflict',
        ], 409);
    }
}
