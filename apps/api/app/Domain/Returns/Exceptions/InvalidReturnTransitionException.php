<?php

namespace App\Domain\Returns\Exceptions;

use App\Domain\Returns\Enums\ReturnStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class InvalidReturnTransitionException extends RuntimeException
{
    public static function make(ReturnStatus $from, ReturnStatus $to): self
    {
        return new self("Return cannot transition from \"{$from->value}\" to \"{$to->value}\".");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'invalid_return_transition',
        ], 409);
    }
}
