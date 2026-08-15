<?php

use App\Domain\CustomerIntelligence\Application\RecomputeCustomerMetrics;
use App\Domain\CustomerIntelligence\Models\CustomerTagAssignment;
use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use App\Shared\Commerce\Models\OutboxEvent;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

function payFor(Order $order, int $amount): void
{
    Payment::query()->create([
        'order_id' => $order->id,
        'provider' => 'fake',
        'status' => PaymentStatus::Paid->value,
        'currency' => 'USD',
        'amount' => $amount,
        'authorized_amount' => $amount,
        'captured_amount' => $amount,
        'refunded_amount' => 0,
        'external_payment_id' => 'p-'.uniqid(),
    ]);
}

it('assigns first-order then repeat-customer as order count grows, never both at once', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id, 'total_amount' => 1000]);
        payFor($order, 1000);

        app(RecomputeCustomerMetrics::class)->handle($customer->id);

        $tagSlugs = fn () => CustomerTagAssignment::query()->where('customer_id', $customer->id)->with('tag')->get()->pluck('tag.slug');

        expect($tagSlugs())->toContain('first-order')->not->toContain('repeat-customer');

        $secondOrder = Order::factory()->create(['customer_id' => $customer->id, 'total_amount' => 1000]);
        payFor($secondOrder, 1000);
        app(RecomputeCustomerMetrics::class)->handle($customer->id);

        expect($tagSlugs())->toContain('repeat-customer')->not->toContain('first-order');
    });
});

it('assigns vip once lifetime value crosses the configured threshold and fires CustomerBecameVip once', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();
        $threshold = (int) config('customer_intelligence.vip_lifetime_value_amount');

        $order = Order::factory()->create(['customer_id' => $customer->id, 'total_amount' => $threshold - 100]);
        payFor($order, $threshold - 100);
        app(RecomputeCustomerMetrics::class)->handle($customer->id);

        expect(CustomerTagAssignment::query()->where('customer_id', $customer->id)->whereHas('tag', fn ($q) => $q->where('slug', 'vip'))->exists())->toBeFalse();

        $order2 = Order::factory()->create(['customer_id' => $customer->id, 'total_amount' => 200]);
        payFor($order2, 200);
        app(RecomputeCustomerMetrics::class)->handle($customer->id);

        expect(CustomerTagAssignment::query()->where('customer_id', $customer->id)->whereHas('tag', fn ($q) => $q->where('slug', 'vip'))->exists())->toBeTrue();
        expect(OutboxEvent::query()->where('event_type', 'CustomerBecameVip')->count())->toBe(1);

        // Recomputing again while still VIP does not re-fire the event.
        app(RecomputeCustomerMetrics::class)->handle($customer->id);
        expect(OutboxEvent::query()->where('event_type', 'CustomerBecameVip')->count())->toBe(1);
    });
});

it('assigns inactive after the configured number of days without an order, and removes it once a new order lands', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();
        $inactiveAfterDays = (int) config('customer_intelligence.inactive_after_days');

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 1000,
            'created_at' => now()->subDays($inactiveAfterDays + 1),
        ]);
        payFor($order, 1000);

        app(RecomputeCustomerMetrics::class)->handle($customer->id);

        expect(CustomerTagAssignment::query()->where('customer_id', $customer->id)->whereHas('tag', fn ($q) => $q->where('slug', 'inactive'))->exists())->toBeTrue();
        expect(OutboxEvent::query()->where('event_type', 'CustomerBecameInactive')->count())->toBe(1);

        $freshOrder = Order::factory()->create(['customer_id' => $customer->id, 'total_amount' => 1000]);
        payFor($freshOrder, 1000);
        app(RecomputeCustomerMetrics::class)->handle($customer->id);

        expect(CustomerTagAssignment::query()->where('customer_id', $customer->id)->whereHas('tag', fn ($q) => $q->where('slug', 'inactive'))->exists())->toBeFalse();

        // The fresh order also flips first-order -> repeat-customer in
        // this same recompute, so two CustomerTagRemoved events fire
        // (inactive, first-order) — filter to the one this test cares about.
        $inactiveRemovedEvents = OutboxEvent::query()
            ->where('event_type', 'CustomerTagRemoved')
            ->get()
            ->filter(fn (OutboxEvent $event) => $event->payload['tag'] === 'inactive');
        expect($inactiveRemovedEvents)->toHaveCount(1);
    });
});
