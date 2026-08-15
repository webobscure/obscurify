<?php

use App\Domain\CustomerIntelligence\Models\CustomerMetric;
use App\Domain\Customers\Application\UpdateCustomerProfile;
use App\Domain\Customers\Models\Customer;
use App\Domain\Financial\Application\ApplyRefundCompletion;
use App\Domain\Financial\Enums\RefundStatus;
use App\Domain\Financial\Models\Refund;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Returns\Application\CompleteReturn;
use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Models\ReturnRequest;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Verifies the actual production call sites — not RecomputeCustomerMetrics
 * in isolation (see CustomerMetricsTest/SegmentRecalculationTest for
 * that) — really do trigger a metrics recompute, per spec section 8's
 * event list. CompleteCheckout (OrderCreated) is covered end-to-end by
 * checkout.spec.ts's Playwright flow already asserting on order state;
 * this file covers the three PHP-only call sites Playwright doesn't
 * reach (a completed refund, a completed return, and a profile update),
 * plus registration.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->host = 'e2e-customer-intelligence.localhost';
    domainForStore($this->store, $this->host);
});

it('registering a new customer account creates a CustomerMetric row', function () {
    $this->withoutMiddleware(ThrottleRequests::class);

    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/register'), [
        'email' => 'metrics-on-register@example.test',
        'password' => 'super-secret-1',
    ])->assertCreated();

    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::query()->where('email', 'metrics-on-register@example.test')->firstOrFail();
        expect(CustomerMetric::query()->where('customer_id', $customer->id)->exists())->toBeTrue();
    });
});

it('updating a customer profile re-evaluates metrics/segments', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();
        expect(CustomerMetric::query()->where('customer_id', $customer->id)->exists())->toBeFalse();

        app(UpdateCustomerProfile::class)->handle($customer, ['first_name' => 'Ada']);

        expect(CustomerMetric::query()->where('customer_id', $customer->id)->exists())->toBeTrue();
    });
});

it('a completed refund updates the customers total_spent and refund_count', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id, 'total_amount' => 10000]);

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'provider' => 'fake',
            'status' => PaymentStatus::Paid->value,
            'currency' => 'USD',
            'amount' => 10000,
            'authorized_amount' => 10000,
            'captured_amount' => 10000,
            'refunded_amount' => 0,
            'external_payment_id' => 'p1',
        ]);

        $refund = Refund::query()->create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'number' => 1,
            'status' => RefundStatus::Requested->value,
            'currency' => 'USD',
            'amount' => 4000,
            'shipping_amount' => 0,
            'adjustment_amount' => 0,
            'requested_at' => now(),
        ]);

        app(ApplyRefundCompletion::class)->handle($refund->fresh(), $payment->fresh(), $order->fresh());

        $metric = CustomerMetric::query()->where('customer_id', $customer->id)->firstOrFail();
        expect($metric->total_spent_amount)->toBe(6000)
            ->and($metric->refund_count)->toBe(1);
    });
});

it('a completed return updates the customers return_count', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id, 'total_amount' => 5000]);

        $returnRequest = ReturnRequest::query()->create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'number' => 1,
            'status' => ReturnStatus::Inspection->value,
            'requested_at' => now(),
        ]);

        app(CompleteReturn::class)->handle($returnRequest);

        $metric = CustomerMetric::query()->where('customer_id', $customer->id)->firstOrFail();
        expect($metric->return_count)->toBe(1);
    });
});
