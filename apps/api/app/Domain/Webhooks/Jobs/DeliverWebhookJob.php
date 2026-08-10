<?php

namespace App\Domain\Webhooks\Jobs;

use App\Domain\Stores\Models\Store;
use App\Domain\Webhooks\Enums\WebhookDeliveryStatus;
use App\Domain\Webhooks\Models\WebhookDelivery;
use App\Shared\Commerce\Models\OutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Signs and POSTs one WebhookDelivery's event payload to its
 * subscription's target_url — the outbound counterpart to
 * FakePaymentProvider/FakeShippingProvider's inbound `verifyWebhook()`,
 * using the exact same crypto convention (hash_hmac sha256, hex,
 * hash_equals on the receiving side) so a subscriber verifies deliveries
 * the same way this platform already verifies provider webhooks.
 *
 * Deliberately never throws on a failed HTTP attempt (a non-2xx response
 * or a network error) — that's business as usual for a webhook target,
 * not a queue-worker failure, and throwing would hand retry timing to
 * Laravel's $tries/backoff/failed() machinery, which only fires for a
 * *queued* dispatch and silently no-ops (or worse, surfaces the
 * exception to the caller) on the `sync` driver this test suite runs
 * on. Instead this job tracks its own attempt_count/next_retry_at on
 * the WebhookDelivery row and marks it `exhausted` itself once
 * MAX_ATTEMPTS is reached; RetryFailedWebhookDeliveriesCommand is what
 * re-dispatches a `failed` delivery once `next_retry_at` has passed.
 */
final class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const int MAX_ATTEMPTS = 6;

    /**
     * @var int[]
     */
    private const array BACKOFF_SECONDS = [10, 30, 60, 300, 900, 3600];

    public function __construct(public readonly string $webhookDeliveryId) {}

    public function handle(TenantContext $tenantContext): void
    {
        $delivery = WebhookDelivery::withoutGlobalScopes()->find($this->webhookDeliveryId);

        if ($delivery === null || $delivery->status === WebhookDeliveryStatus::Succeeded) {
            return;
        }

        $store = Store::query()->find($delivery->store_id);

        if ($store === null) {
            return;
        }

        $tenantContext->scope($store, function () use ($delivery) {
            $delivery = $delivery->fresh();
            $subscription = $delivery->subscription;
            $event = OutboxEvent::withoutGlobalScopes()->find($delivery->outbox_event_id);

            if ($subscription === null || $event === null) {
                return;
            }

            $body = json_encode([
                'id' => $delivery->id,
                'event_type' => $event->event_type,
                'aggregate_type' => $event->aggregate_type,
                'aggregate_id' => $event->aggregate_id,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
                'data' => $event->payload,
            ], JSON_THROW_ON_ERROR);

            $signature = hash_hmac('sha256', $body, $subscription->secret);
            $attemptNumber = $delivery->attempt_count + 1;

            try {
                $response = Http::withBody($body, 'application/json')
                    ->withHeaders([
                        'X-Obscurify-Webhook-Id' => $delivery->id,
                        'X-Obscurify-Webhook-Event' => $event->event_type,
                        'X-Obscurify-Webhook-Signature' => $signature,
                    ])
                    ->timeout(10)
                    ->post($subscription->target_url);

                $this->recordAttempt($delivery, $attemptNumber, $response->successful(), $response->status(), null);
            } catch (Throwable $e) {
                $this->recordAttempt($delivery, $attemptNumber, false, null, substr($e->getMessage(), 0, 255));
            }
        });
    }

    private function recordAttempt(WebhookDelivery $delivery, int $attemptNumber, bool $succeeded, ?int $responseCode, ?string $errorMessage): void
    {
        if ($succeeded) {
            $delivery->update([
                'attempt_count' => $attemptNumber,
                'response_code' => $responseCode,
                'error_message' => null,
                'last_attempted_at' => now(),
                'status' => WebhookDeliveryStatus::Succeeded->value,
                'delivered_at' => now(),
            ]);

            return;
        }

        $exhausted = $attemptNumber >= self::MAX_ATTEMPTS;

        $delivery->update([
            'attempt_count' => $attemptNumber,
            'response_code' => $responseCode,
            'error_message' => $errorMessage,
            'last_attempted_at' => now(),
            'status' => $exhausted ? WebhookDeliveryStatus::Exhausted->value : WebhookDeliveryStatus::Failed->value,
            'next_retry_at' => $exhausted ? null : now()->addSeconds(self::BACKOFF_SECONDS[$attemptNumber - 1] ?? 3600),
        ]);
    }
}
