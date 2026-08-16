<?php

namespace App\Domain\RussianCommerce\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class InvalidFiscalizationCallbackSignatureException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Invalid fiscalization callback signature.');
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'invalid_fiscalization_callback_signature',
        ], 401);
    }
}
