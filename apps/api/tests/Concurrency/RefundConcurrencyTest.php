<?php

use App\Domain\Financial\Application\ApplyRefundCompletion;
use App\Domain\Financial\Application\ProcessRefundWebhook;
use App\Domain\Financial\Models\LedgerEntry;
use App\Domain\Financial\Models\LedgerTransaction;
use App\Domain\Financial\Models\Refund;
use App\Domain\Financial\Models\RefundItem;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Support\WebhookEvent;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Refunds + Financial Ledger (spec section 18): real PostgreSQL
 * concurrency — the same Refund must never be applied (ledger-posted)
 * twice, whether that race comes from two literal duplicate webhook
 * deliveries of the identical event, or from two independent completion
 * attempts racing at the application layer.
 */
beforeEach(function () {
    $this->withCredentials();

    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    domainForStore($this->store, 'refund-concurrency-store.localhost');

    [$this->product, $this->variant] = productWithStock($this->store, 10);
});

afterEach(function () {
    DB::table('stores')->where('id', $this->store->id)->delete();
    DB::table('users')->where('id', $this->user->id)->delete();
});

it('processes two simultaneous identical refund success webhooks exactly once: one ledger transaction, refunded once', function () {
    ['order_id' => $orderId, 'return_item_id' => $returnItemId] = completedReturnFor('refund-concurrency-store.localhost', $this->user, $this->store, $this->variant->id, 1);

    $payment = app(TenantContext::class)->scope($this->store, fn () => Payment::query()->where('order_id', $orderId)->firstOrFail());

    $created = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/orders/{$orderId}/refunds", [
        'items' => [['return_item_id' => $returnItemId, 'quantity' => 1, 'amount' => $payment->amount]],
        'provider' => 'fake',
    ], array_merge(tenantHeader($this->store), ['Idempotency-Key' => 'refund-concurrency-webhook-1']))->assertCreated();

    $externalRefundId = $created->json('data.provider_reference');
    $eventId = (string) Str::ulid();

    $deliverWebhook = function () use ($eventId, $externalRefundId, $payment) {
        $event = new WebhookEvent(
            eventId: $eventId,
            externalPaymentId: null,
            eventType: 'refund.updated',
            status: 'succeeded',
            amount: $payment->amount,
            currency: $payment->currency,
            occurredAt: Carbon::now(),
            externalRefundId: $externalRefundId,
        );

        $raw = json_encode([
            'event_id' => $eventId,
            'external_refund_id' => $externalRefundId,
            'event_type' => 'refund.updated',
            'status' => 'succeeded',
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'timestamp' => now()->timestamp,
        ]);

        app(ProcessRefundWebhook::class)->handle('fake', $event, $raw);

        return 'ok';
    };

    $results = runConcurrently([$deliverWebhook, $deliverWebhook]);

    expect($results[0]['ok'])->toBeTrue()
        ->and($results[1]['ok'])->toBeTrue();

    app(TenantContext::class)->scope($this->store, function () use ($payment) {
        $freshPayment = Payment::query()->whereKey($payment->id)->firstOrFail();
        expect($freshPayment->refunded_amount)->toBe($payment->amount);

        expect(LedgerTransaction::query()->where('reference_type', Refund::class)->count())->toBe(1);
        expect(LedgerEntry::query()->count())->toBe(4); // 2 capture + 2 refund, never more
    });
});

it('never applies the same refund twice under two simultaneous completion attempts', function () {
    ['order_id' => $orderId, 'return_item_id' => $returnItemId] = completedReturnFor('refund-concurrency-store.localhost', $this->user, $this->store, $this->variant->id, 1);

    $refundId = app(TenantContext::class)->scope($this->store, function () use ($orderId, $returnItemId) {
        $order = Order::query()->whereKey($orderId)->firstOrFail();
        $payment = Payment::query()->where('order_id', $orderId)->firstOrFail();

        $refund = Refund::query()->create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'number' => 1001,
            'status' => 'requested',
            'currency' => $payment->currency,
            'amount' => $payment->amount,
            'requested_at' => now(),
        ]);

        RefundItem::query()->create([
            'refund_id' => $refund->id,
            'return_item_id' => $returnItemId,
            'quantity' => 1,
            'amount' => $payment->amount,
        ]);

        return $refund->id;
    });

    $complete = function () use ($refundId) {
        return app(TenantContext::class)->scope($this->store, function () use ($refundId) {
            $refund = Refund::query()->whereKey($refundId)->firstOrFail();
            $payment = Payment::query()->whereKey($refund->payment_id)->firstOrFail();
            $order = Order::query()->whereKey($refund->order_id)->firstOrFail();

            DB::transaction(function () use ($refund, $payment, $order) {
                $lockedRefund = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
                $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
                $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

                app(ApplyRefundCompletion::class)->handle($lockedRefund, $lockedPayment, $lockedOrder);
            });

            return 'ok';
        });
    };

    $results = runConcurrently([$complete, $complete]);

    expect($results[0]['ok'])->toBeTrue()
        ->and($results[1]['ok'])->toBeTrue();

    app(TenantContext::class)->scope($this->store, function () use ($refundId, $orderId) {
        $payment = Payment::query()->where('order_id', $orderId)->firstOrFail();
        $refund = Refund::query()->whereKey($refundId)->firstOrFail();

        expect($refund->status->value)->toBe('completed')
            ->and($payment->refunded_amount)->toBe($refund->amount);

        expect(LedgerTransaction::query()->where('reference_type', Refund::class)->where('reference_id', $refundId)->count())->toBe(1);
    });
});
