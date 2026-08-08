<?php

namespace App\Domain\Payments\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class MalformedWebhookPayloadException extends RuntimeException
{
    public static function make(string $reason): self
    {
        return new self("Malformed webhook payload: {$reason}");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'malformed_webhook_payload',
        ], 422);
    }
}
