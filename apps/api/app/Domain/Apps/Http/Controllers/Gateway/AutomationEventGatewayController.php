<?php

namespace App\Domain\Apps\Http\Controllers\Gateway;

use App\Domain\Apps\Support\CurrentAppContext;
use App\Http\Controllers\Controller;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lets an installed app push an event into this platform's own Platform
 * Event Bus — the inbound counterpart to DeliverWebhookJob's outbound
 * delivery. Backs the AppWebhookReceived trigger (spec section 3's
 * example list) and the general "apps can react to the outside world
 * and tell this platform about it" case: an app POSTs here, the
 * platform records a real OutboxEvent, and any workflow triggered on
 * AppWebhookReceived (or a custom event_type the app supplies) picks it
 * up exactly like any other trigger — see
 * docs/architecture/automation.md §3.
 */
final class AutomationEventGatewayController extends Controller
{
    public function __construct(
        private readonly CurrentAppContext $currentAppContext,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_type' => ['sometimes', 'string', 'max:100'],
            'payload' => ['sometimes', 'array'],
        ]);

        $installedApp = $this->currentAppContext->installedApp();

        $event = $this->recordOutboxEvent->handle(
            $data['event_type'] ?? 'AppWebhookReceived',
            'InstalledApp',
            $installedApp->id,
            ['installed_app_id' => $installedApp->id, 'store_id' => $installedApp->store_id, ...($data['payload'] ?? [])],
        );

        return response()->json(['id' => $event->id], 201);
    }
}
