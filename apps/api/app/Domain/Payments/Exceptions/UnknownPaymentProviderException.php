<?php

namespace App\Domain\Payments\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class UnknownPaymentProviderException extends RuntimeException
{
    public static function forCode(string $code): self
    {
        return new self(__('payments.unknown_provider', ['code' => $code]));
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'unknown_payment_provider',
        ], 422);
    }
}
