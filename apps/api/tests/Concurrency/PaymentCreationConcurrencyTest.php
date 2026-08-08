<?php

use App\Domain\Orders\Enums\FinancialStatus;
use App\Domain\Orders\Enums\FulfillmentStatus;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Application\CreatePayment;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use App\Shared\Commerce\Application\IdempotencyKeyStore;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);

    $this->order = app(TenantContext::class)->scope($this->store, function () {
        return Order::query()->create([
            'number' => 5001,
            'currency' => $this->store->default_currency,
            'items_subtotal_amount' => 2000,
            'shipping_amount' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 2000,
            'order_status' => OrderStatus::Open->value,
            'financial_status' => FinancialStatus::Pending->value,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled->value,
        ]);
    });
});

afterEach(function () {
    // Genuinely committed rows (no RefreshDatabase in this suite — see
    // Pest.php), so cleanup is manual; deleting the store cascades
    // everything else (orders, payments, idempotency keys, ...).
    DB::table('stores')->where('id', $this->store->id)->delete();
    DB::table('users')->where('id', $this->user->id)->delete();
});

it('lets exactly one of two simultaneous payment creations with the same Idempotency-Key win', function () {
    $key = 'race-key-'.Str::random(8);

    $createWithKey = function () use ($key) {
        return app(TenantContext::class)->scope($this->store, function () use ($key) {
            $order = Order::query()->whereKey($this->order->id)->firstOrFail();
            $requestHash = hash('sha256', $order->id.'|fake');

            $result = app(IdempotencyKeyStore::class)->handle('payment.create', $key, $requestHash, function () use ($order) {
                $payment = app(CreatePayment::class)->handle($order, 'fake');

                return ['status' => 201, 'body' => ['id' => $payment->id]];
            });

            return $result['body'];
        });
    };

    $results = runConcurrently([$createWithKey, $createWithKey]);

    expect($results[0]['ok'])->toBeTrue()
        ->and($results[1]['ok'])->toBeTrue()
        ->and($results[0]['value'])->toBe($results[1]['value']);

    app(TenantContext::class)->scope($this->store, function () use ($results) {
        expect(Payment::query()->where('order_id', $this->order->id)->count())->toBe(1)
            ->and(Payment::query()->firstOrFail()->id)->toBe($results[0]['value']['id']);
    });
});
