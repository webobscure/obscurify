<?php

namespace App\Domain\Payments\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * The webhook's own timestamp is outside the configured replay-tolerance
 * window (`payments.webhook.replay_tolerance_seconds`) — rejected even
 * though the signature is otherwise valid, since a captured/replayed
 * request would still carry a valid signature for its original payload.
 */
final class WebhookReplayException extends RuntimeException
{
    public static function make(): self
    {
        return new self(__('payments.webhook_replay_rejected'));
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'webhook_replay_rejected',
        ], 422);
    }
}
