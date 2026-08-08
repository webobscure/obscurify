<?php

use App\Domain\Orders\Enums\FinancialStatus;
use App\Domain\Orders\Enums\FulfillmentStatus;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Application\ProcessPaymentWebhook;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentWebhookEvent;
use App\Domain\Payments\Support\WebhookEvent;
use App\Models\User;
use App\Shared\Commerce\Models\OutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);

    [$this->order, $this->payment] = app(TenantContext::class)->scope($this->store, function () {
        $order = Order::query()->create([
            'number' => 6001,
            'currency' => $this->store->default_currency,
            'items_subtotal_amount' => 1500,
            'shipping_amount' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 1500,
            'order_status' => OrderStatus::Open->value,
            'financial_status' => FinancialStatus::Pending->value,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled->value,
        ]);

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'provider' => 'fake',
            'status' => PaymentStatus::Processing->value,
            'currency' => $order->currency,
            'amount' => $order->total_amount,
            'external_payment_id' => 'fake_'.Str::ulid(),
        ]);

        return [$order, $payment];
    });
});

afterEach(function () {
    // Genuinely committed rows (no RefreshDatabase in this suite — see
    // Pest.php), so cleanup is manual; deleting the store cascades
    // everything else.
    DB::table('stores')->where('id', $this->store->id)->delete();
    DB::table('users')->where('id', $this->user->id)->delete();
});

it('processes two simultaneous identical success webhooks exactly once: one transition, one transaction, order paid once', function () {
    $eventId = (string) Str::ulid();
    $externalPaymentId = $this->payment->external_payment_id;
    $amount = $this->payment->amount;
    $currency = $this->payment->currency;

    $deliverWebhook = function () use ($eventId, $externalPaymentId, $amount, $currency) {
        $event = new WebhookEvent(
            eventId: $eventId,
            externalPaymentId: $externalPaymentId,
            eventType: 'payment.updated',
            status: 'succeeded',
            amount: $amount,
            currency: $currency,
            occurredAt: Carbon::now(),
        );

        $raw = json_encode([
            'event_id' => $eventId,
            'external_payment_id' => $externalPaymentId,
            'event_type' => 'payment.updated',
            'status' => 'succeeded',
            'amount' => $amount,
            'currency' => $currency,
            'timestamp' => now()->timestamp,
        ]);

        app(ProcessPaymentWebhook::class)->handle('fake', $event, $raw);

        return 'ok';
    };

    $results = runConcurrently([$deliverWebhook, $deliverWebhook]);

    expect($results[0]['ok'])->toBeTrue()
        ->and($results[1]['ok'])->toBeTrue();

    app(TenantContext::class)->scope($this->store, function () use ($eventId) {
        $payment = Payment::query()->whereKey($this->payment->id)->firstOrFail();
        $order = Order::query()->whereKey($this->order->id)->firstOrFail();

        expect($payment->status->value)->toBe('paid')
            ->and($payment->transactions()->count())->toBe(1)
            ->and($order->financial_status->value)->toBe('paid');

        expect(PaymentWebhookEvent::withoutGlobalScopes()->where('external_event_id', $eventId)->count())->toBe(1);
    });

    $paidOutboxEvents = app(TenantContext::class)->scope(
        $this->store,
        fn () => OutboxEvent::query()->where('event_type', 'PaymentPaid')->count(),
    );
    expect($paidOutboxEvents)->toBe(1);
});
