<?php

namespace App\Domain\Customers\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Deliberately identical message/shape for "not found", "already used",
 * and "expired" — telling a caller which one applies would let them
 * enumerate valid password-reset/verification tokens.
 */
final class InvalidActionTokenException extends RuntimeException
{
    private function __construct()
    {
        parent::__construct('This link is invalid or has expired.');
    }

    public static function make(): self
    {
        return new self;
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage(), 'error' => 'invalid_token'], 422);
    }
}
