<?php

namespace App\Domain\Payments\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class InvalidWebhookSignatureException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Invalid webhook signature.');
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'invalid_webhook_signature',
        ], 401);
    }
}
