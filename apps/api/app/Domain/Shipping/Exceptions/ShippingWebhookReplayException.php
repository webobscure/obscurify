<?php

namespace App\Domain\Shipping\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Mirrors Payments' WebhookReplayException — the webhook's own timestamp
 * is outside the configured replay-tolerance window.
 */
final class ShippingWebhookReplayException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Webhook timestamp is outside the allowed replay window.');
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'webhook_replay_rejected',
        ], 422);
    }
}
