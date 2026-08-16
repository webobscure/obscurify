<?php

namespace App\Domain\RussianCommerce\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class UnknownFiscalizationProviderException extends RuntimeException
{
    public static function forCode(string $code): self
    {
        return new self("Unknown fiscalization provider \"{$code}\".");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'unknown_fiscalization_provider',
        ], 422);
    }
}
