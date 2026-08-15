<?php

use App\Domain\CustomerIntelligence\Application\RecomputeCustomerMetrics;
use App\Domain\CustomerIntelligence\Models\CustomerMetric;
use App\Domain\Customers\Models\Customer;
use App\Domain\Financial\Enums\RefundStatus;
use App\Domain\Financial\Models\Refund;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Models\ReturnRequest;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

it('computes total spent net of refunds, average order value gross, order/refund/return counts, and return rate', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();

        $orderOne = Order::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 10000,
            'created_at' => now()->subDays(20),
        ]);
        Payment::query()->create([
            'order_id' => $orderOne->id,
            'provider' => 'fake',
            'status' => PaymentStatus::Paid->value,
            'currency' => 'USD',
            'amount' => 10000,
            'authorized_amount' => 10000,
            'captured_amount' => 10000,
            'refunded_amount' => 0,
            'external_payment_id' => 'p1',
        ]);

        $orderTwo = Order::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 20000,
            'created_at' => now()->subDays(5),
        ]);
        $paymentTwo = Payment::query()->create([
            'order_id' => $orderTwo->id,
            'provider' => 'fake',
            'status' => PaymentStatus::PartiallyRefunded->value,
            'currency' => 'USD',
            'amount' => 20000,
            'authorized_amount' => 20000,
            'captured_amount' => 20000,
            'refunded_amount' => 5000,
            'external_payment_id' => 'p2',
        ]);

        Refund::query()->create([
            'order_id' => $orderTwo->id,
            'payment_id' => $paymentTwo->id,
            'number' => 1,
            'status' => RefundStatus::Completed->value,
            'currency' => 'USD',
            'amount' => 5000,
            'shipping_amount' => 0,
            'adjustment_amount' => 0,
            'requested_at' => now(),
            'processed_at' => now(),
        ]);

        ReturnRequest::query()->create([
            'order_id' => $orderTwo->id,
            'customer_id' => $customer->id,
            'number' => 1,
            'status' => ReturnStatus::Completed->value,
            'requested_at' => now(),
        ]);

        $metric = app(RecomputeCustomerMetrics::class)->handle($customer->id);

        // Net of refunds: (10000 + 20000) - 5000.
        expect($metric->total_spent_amount)->toBe(25000)
            // Gross average of the two orders' totals: (10000 + 20000) / 2.
            ->and($metric->average_order_value_amount)->toBe(15000)
            ->and($metric->order_count)->toBe(2)
            ->and($metric->refund_count)->toBe(1)
            ->and($metric->return_count)->toBe(1)
            // 1 return / 2 orders = 50.00% = 5000 bps.
            ->and($metric->return_rate_bps)->toBe(5000)
            ->and($metric->lifetime_value_amount)->toBe($metric->total_spent_amount)
            ->and($metric->first_order_at->isSameDay($orderOne->created_at))->toBeTrue()
            ->and($metric->last_order_at->isSameDay($orderTwo->created_at))->toBeTrue();

        expect(CustomerMetric::query()->where('customer_id', $customer->id)->count())->toBe(1);
    });
});

it('upserts rather than duplicating on a second recompute', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();
        Order::factory()->create(['customer_id' => $customer->id, 'total_amount' => 5000]);

        app(RecomputeCustomerMetrics::class)->handle($customer->id);
        app(RecomputeCustomerMetrics::class)->handle($customer->id);

        expect(CustomerMetric::query()->where('customer_id', $customer->id)->count())->toBe(1);
    });
});

it('a customer with no orders has zeroed metrics and no first/last order timestamps', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();

        $metric = app(RecomputeCustomerMetrics::class)->handle($customer->id);

        expect($metric->order_count)->toBe(0)
            ->and($metric->total_spent_amount)->toBe(0)
            ->and($metric->return_rate_bps)->toBe(0)
            ->and($metric->first_order_at)->toBeNull()
            ->and($metric->last_order_at)->toBeNull();
    });
});
