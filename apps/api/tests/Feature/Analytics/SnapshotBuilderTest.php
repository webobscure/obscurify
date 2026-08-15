<?php

use App\Domain\Analytics\Models\AnalyticsSnapshot;
use App\Domain\Analytics\Support\AnalyticsProjector;
use App\Domain\Analytics\Support\AnalyticsSnapshotBuilder;
use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

it('backfills a date range, producing a snapshot row for every day even with no events', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $from = now()->subDays(4);
        $to = now();

        app(AnalyticsSnapshotBuilder::class)->buildRange($this->store->id, $from, $to);

        // 5 days inclusive.
        expect(AnalyticsSnapshot::query()->where('metric_key', 'order_count')->count())->toBe(5);
        expect(AnalyticsSnapshot::query()->where('metric_key', 'order_count')->sum('value'))->toBe(0);
    });
});

it('reflects an event backdated into the middle of the rebuilt range', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();
        $order = Order::query()->create([
            'number' => 1, 'customer_id' => $customer->id, 'currency' => 'USD',
            'items_subtotal_amount' => 2000, 'shipping_amount' => 0, 'discount_amount' => 0, 'tax_amount' => 0,
            'total_amount' => 2000, 'order_status' => 'open', 'financial_status' => 'paid', 'fulfillment_status' => 'unfulfilled',
            'email' => $customer->email,
        ]);

        $threeDaysAgo = now()->subDays(3);
        $event = app(RecordOutboxEvent::class)->handle('OrderPaymentConfirmed', 'Order', $order->id, ['order_id' => $order->id, 'store_id' => $this->store->id]);
        $event->update(['occurred_at' => $threeDaysAgo]);
        app(AnalyticsProjector::class)->project($event->fresh());

        // The real-time hook already aggregated that one day; rebuild
        // the whole range to prove AnalyticsSnapshotBuilder reproduces
        // the same result independently (the actual backfill use case).
        app(AnalyticsSnapshotBuilder::class)->buildRange($this->store->id, now()->subDays(6), now());

        $snapshot = AnalyticsSnapshot::query()->where('metric_key', 'gross_revenue')->where('period_date', $threeDaysAgo->toDateString())->first();
        expect($snapshot->value)->toBe(2000);

        // Every other day in range stays zero.
        $otherDay = AnalyticsSnapshot::query()->where('metric_key', 'gross_revenue')->where('period_date', now()->toDateString())->first();
        expect($otherDay->value)->toBe(0);
    });
});

it('buildRangeForAllStores only writes snapshots for the store that actually had activity', function () {
    $storeB = createStoreForUser(User::factory()->create());

    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();
        $order = Order::query()->create([
            'number' => 1, 'customer_id' => $customer->id, 'currency' => 'USD',
            'items_subtotal_amount' => 4000, 'shipping_amount' => 0, 'discount_amount' => 0, 'tax_amount' => 0,
            'total_amount' => 4000, 'order_status' => 'open', 'financial_status' => 'paid', 'fulfillment_status' => 'unfulfilled',
            'email' => $customer->email,
        ]);
        $event = app(RecordOutboxEvent::class)->handle('OrderPaymentConfirmed', 'Order', $order->id, ['order_id' => $order->id, 'store_id' => $this->store->id]);
        app(AnalyticsProjector::class)->project($event);
    });

    app(AnalyticsSnapshotBuilder::class)->buildRangeForAllStores(now(), now());

    app(TenantContext::class)->scope($this->store, function () {
        expect(AnalyticsSnapshot::query()->where('metric_key', 'gross_revenue')->where('period_date', now()->toDateString())->value('value'))->toBe(4000);
    });

    app(TenantContext::class)->scope($storeB, function () {
        expect(AnalyticsSnapshot::query()->where('metric_key', 'gross_revenue')->where('period_date', now()->toDateString())->value('value'))->toBe(0);
    });
});
