<?php

namespace App\Domain\Search\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class UnknownSearchProviderException extends RuntimeException
{
    public static function forCode(string $code): self
    {
        return new self("Unknown search provider \"{$code}\".");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'unknown_search_provider',
        ], 422);
    }
}
