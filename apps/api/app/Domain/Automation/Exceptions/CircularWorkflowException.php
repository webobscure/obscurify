<?php

namespace App\Domain\Automation\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown by WorkflowLoopGuard::assertPublishable() when a workflow's own
 * "Publish event" action targets the exact event_type its own trigger
 * listens for — a direct, statically-detectable self-trigger loop that
 * should never be allowed to publish (spec section 13: "Detect circular
 * workflows"). Indirect/transitive cycles can't be caught statically and
 * are instead bounded at runtime by WorkflowLoopGuard::MAX_DEPTH — see
 * docs/adr/025-automation-engine.md.
 */
final class CircularWorkflowException extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forEventType(string $eventType): self
    {
        return new self("This workflow's own actions would re-publish its trigger event ({$eventType}), which would trigger itself. Remove the circular \"Publish event\" action or change the trigger.");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage(), 'error' => 'circular_workflow'], 422);
    }
}
