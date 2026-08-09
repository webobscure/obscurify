<?php

namespace App\Domain\Shipping\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 403, not 401 like Payments' equivalent — a deliberate correction, not an
 * inconsistency (see the architecture review's TD-35): this route carries
 * no Sanctum auth to collide with, and 403 ("credential presented and
 * rejected") is the semantically accurate code for a failed signature
 * check versus 401 ("no valid credential presented at all").
 */
final class InvalidShippingWebhookSignatureException extends RuntimeException
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
        ], 403);
    }
}
