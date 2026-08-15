<?php

use App\Domain\Analytics\Models\AnalyticsSnapshot;
use App\Domain\Analytics\Support\AnalyticsProjector;
use App\Domain\Catalog\Models\Product;
use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Models\User;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

function createPaidOrder(string $storeId, string $customerId, int $totalAmount, int $number): Order
{
    $customer = Customer::query()->find($customerId);

    return Order::query()->create([
        'number' => $number, 'customer_id' => $customerId, 'currency' => 'USD',
        'items_subtotal_amount' => $totalAmount, 'shipping_amount' => 0, 'discount_amount' => 0, 'tax_amount' => 0,
        'total_amount' => $totalAmount, 'order_status' => 'open', 'financial_status' => 'paid', 'fulfillment_status' => 'unfulfilled',
        'email' => $customer?->email,
    ]);
}

it('aggregates gross revenue, order counts, and average order value correctly for one day', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customerA = Customer::factory()->create();
        $customerB = Customer::factory()->create();

        $orderA = createPaidOrder($this->store->id, $customerA->id, 10000, 1);
        $orderB = createPaidOrder($this->store->id, $customerB->id, 5000, 2);

        foreach ([$orderA, $orderB] as $order) {
            $created = app(RecordOutboxEvent::class)->handle('OrderCreated', 'Order', $order->id, ['order_id' => $order->id, 'store_id' => $this->store->id]);
            app(AnalyticsProjector::class)->project($created);
            $paid = app(RecordOutboxEvent::class)->handle('OrderPaymentConfirmed', 'Order', $order->id, ['order_id' => $order->id, 'store_id' => $this->store->id]);
            app(AnalyticsProjector::class)->project($paid);
        }

        $today = now()->toDateString();
        expect(AnalyticsSnapshot::query()->where('metric_key', 'gross_revenue')->where('period_date', $today)->value('value'))->toBe(15000);
        expect(AnalyticsSnapshot::query()->where('metric_key', 'order_count')->where('period_date', $today)->value('value'))->toBe(2);
        expect(AnalyticsSnapshot::query()->where('metric_key', 'paid_order_count')->where('period_date', $today)->value('value'))->toBe(2);
        expect(AnalyticsSnapshot::query()->where('metric_key', 'average_order_value')->where('period_date', $today)->value('value'))->toBe(7500);
        expect(AnalyticsSnapshot::query()->where('metric_key', 'net_revenue')->where('period_date', $today)->value('value'))->toBe(15000);
    });
});

it('deducts refunds from net revenue but leaves gross revenue untouched', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();
        $order = createPaidOrder($this->store->id, $customer->id, 10000, 1);

        $paid = app(RecordOutboxEvent::class)->handle('OrderPaymentConfirmed', 'Order', $order->id, ['order_id' => $order->id, 'store_id' => $this->store->id]);
        app(AnalyticsProjector::class)->project($paid);

        $refund = app(RecordOutboxEvent::class)->handle('RefundCompleted', 'Refund', (string) Str::ulid(), ['order_id' => $order->id, 'store_id' => $this->store->id, 'amount' => 3000, 'currency' => 'USD']);
        app(AnalyticsProjector::class)->project($refund);

        $today = now()->toDateString();
        expect(AnalyticsSnapshot::query()->where('metric_key', 'gross_revenue')->where('period_date', $today)->value('value'))->toBe(10000);
        expect(AnalyticsSnapshot::query()->where('metric_key', 'refund_amount')->where('period_date', $today)->value('value'))->toBe(3000);
        expect(AnalyticsSnapshot::query()->where('metric_key', 'net_revenue')->where('period_date', $today)->value('value'))->toBe(7000);
    });
});

it('builds a top_products leaderboard summed by revenue', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();
        $productA = Product::factory()->create(['title' => 'Widget A']);
        $productB = Product::factory()->create(['title' => 'Widget B']);

        $order = createPaidOrder($this->store->id, $customer->id, 15000, 1);
        OrderItem::query()->create([
            'order_id' => $order->id, 'product_id' => $productA->id, 'product_variant_id' => null,
            'product_title' => 'Widget A', 'variant_title' => 'Default', 'sku' => 'A',
            'unit_price_amount' => 10000, 'quantity' => 1, 'line_total_amount' => 10000, 'currency' => 'USD',
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id, 'product_id' => $productB->id, 'product_variant_id' => null,
            'product_title' => 'Widget B', 'variant_title' => 'Default', 'sku' => 'B',
            'unit_price_amount' => 5000, 'quantity' => 1, 'line_total_amount' => 5000, 'currency' => 'USD',
        ]);

        $paid = app(RecordOutboxEvent::class)->handle('OrderPaymentConfirmed', 'Order', $order->id, ['order_id' => $order->id, 'store_id' => $this->store->id]);
        app(AnalyticsProjector::class)->project($paid);

        $breakdown = AnalyticsSnapshot::query()->where('metric_key', 'top_products')->where('period_date', now()->toDateString())->value('breakdown');
        expect($breakdown[$productA->id]['value'])->toBe(10000);
        expect($breakdown[$productB->id]['value'])->toBe(5000);
    });
});

it('counts a returning customer only once their second order lands', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();

        $order1 = createPaidOrder($this->store->id, $customer->id, 1000, 1);
        $created1 = app(RecordOutboxEvent::class)->handle('OrderCreated', 'Order', $order1->id, ['order_id' => $order1->id, 'store_id' => $this->store->id]);
        app(AnalyticsProjector::class)->project($created1);

        $today = now()->toDateString();
        expect(AnalyticsSnapshot::query()->where('metric_key', 'returning_customers')->where('period_date', $today)->value('value'))->toBe(0);

        $order2 = createPaidOrder($this->store->id, $customer->id, 2000, 2);
        $created2 = app(RecordOutboxEvent::class)->handle('OrderCreated', 'Order', $order2->id, ['order_id' => $order2->id, 'store_id' => $this->store->id]);
        app(AnalyticsProjector::class)->project($created2);

        expect(AnalyticsSnapshot::query()->where('metric_key', 'returning_customers')->where('period_date', $today)->value('value'))->toBe(1);
        // repeat_purchase_rate is basis points: 1 returning / 2 orders = 5000 bps (50%).
        expect(AnalyticsSnapshot::query()->where('metric_key', 'repeat_purchase_rate')->where('period_date', $today)->value('value'))->toBe(5000);
    });
});
