<?php

use App\Domain\Analytics\Models\AnalyticsEvent;
use App\Domain\Analytics\Support\AnalyticsProjector;
use App\Domain\Catalog\Models\Product;
use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\OrderShippingLine;
use App\Domain\Promotions\Models\DiscountApplication;
use App\Models\User;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

it('projects an irrelevant event type into nothing', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $event = app(RecordOutboxEvent::class)->handle('SomeUnrelatedEvent', 'Order', (string) Str::ulid(), []);
        app(AnalyticsProjector::class)->project($event);

        expect(AnalyticsEvent::query()->count())->toBe(0);
    });
});

it('projects OrderCreated with customer_id and detects the first order correctly', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();
        $order = Order::query()->create([
            'number' => 1, 'customer_id' => $customer->id, 'currency' => 'USD',
            'items_subtotal_amount' => 1000, 'shipping_amount' => 0, 'discount_amount' => 0, 'tax_amount' => 0,
            'total_amount' => 1000, 'order_status' => 'open', 'financial_status' => 'pending', 'fulfillment_status' => 'unfulfilled',
            'email' => $customer->email,
        ]);

        $event1 = app(RecordOutboxEvent::class)->handle('OrderCreated', 'Order', $order->id, ['order_id' => $order->id, 'store_id' => $this->store->id]);
        app(AnalyticsProjector::class)->project($event1);

        $projected1 = AnalyticsEvent::query()->where('outbox_event_id', $event1->id)->firstOrFail();
        expect($projected1->customer_id)->toBe($customer->id);
        expect($projected1->amount)->toBe(1000);
        expect($projected1->payload['is_first_order'])->toBeTrue();

        // A second order for the same customer is not their first.
        $order2 = Order::query()->create([
            'number' => 2, 'customer_id' => $customer->id, 'currency' => 'USD',
            'items_subtotal_amount' => 500, 'shipping_amount' => 0, 'discount_amount' => 0, 'tax_amount' => 0,
            'total_amount' => 500, 'order_status' => 'open', 'financial_status' => 'pending', 'fulfillment_status' => 'unfulfilled',
            'email' => $customer->email,
        ]);
        $event2 = app(RecordOutboxEvent::class)->handle('OrderCreated', 'Order', $order2->id, ['order_id' => $order2->id, 'store_id' => $this->store->id]);
        app(AnalyticsProjector::class)->project($event2);

        $projected2 = AnalyticsEvent::query()->where('outbox_event_id', $event2->id)->firstOrFail();
        expect($projected2->payload['is_first_order'])->toBeFalse();
    });
});

it('projects OrderPaymentConfirmed with the full line item, discount, and shipping breakdown', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['title' => 'Widget']);

        $order = Order::query()->create([
            'number' => 1, 'customer_id' => $customer->id, 'currency' => 'USD',
            'items_subtotal_amount' => 10000, 'shipping_amount' => 500, 'discount_amount' => 1000, 'tax_amount' => 0,
            'total_amount' => 9500, 'order_status' => 'open', 'financial_status' => 'paid', 'fulfillment_status' => 'unfulfilled',
            'email' => $customer->email,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id, 'product_id' => $product->id, 'product_variant_id' => null,
            'product_title' => 'Widget', 'variant_title' => 'Default', 'sku' => 'SKU-1',
            'unit_price_amount' => 10000, 'quantity' => 1, 'line_total_amount' => 10000, 'currency' => 'USD',
        ]);

        OrderShippingLine::query()->create([
            'order_id' => $order->id, 'provider' => 'flat_rate', 'service_code' => 'standard',
            'title' => 'Standard Shipping', 'price_amount' => 500, 'currency' => 'USD',
        ]);

        DiscountApplication::query()->create([
            'order_id' => $order->id, 'promotion_id' => null, 'discount_code_id' => null,
            'promotion_name' => 'Sale', 'code' => 'SALE10', 'action_type' => 'percentage_off', 'target' => 'order',
            'amount' => 1000, 'currency' => 'USD',
        ]);

        $event = app(RecordOutboxEvent::class)->handle('OrderPaymentConfirmed', 'Order', $order->id, ['order_id' => $order->id, 'store_id' => $this->store->id]);
        app(AnalyticsProjector::class)->project($event);

        $projected = AnalyticsEvent::query()->where('outbox_event_id', $event->id)->firstOrFail();
        expect($projected->amount)->toBe(9500);
        expect($projected->customer_id)->toBe($customer->id);
        expect($projected->payload['line_items'][0]['product_id'])->toBe($product->id);
        expect($projected->payload['line_items'][0]['amount'])->toBe(10000);
        expect($projected->payload['discounts'][0]['label'])->toBe('Sale');
        expect($projected->payload['discounts'][0]['amount'])->toBe(1000);
        expect($projected->payload['shipping']['label'])->toBe('Standard Shipping');
    });
});

it('is idempotent for the same outbox event under a concurrent-style double claim', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $event = app(RecordOutboxEvent::class)->handle('CustomerCreated', 'Customer', (string) Str::ulid(), []);

        app(AnalyticsProjector::class)->project($event);
        app(AnalyticsProjector::class)->project($event);

        expect(AnalyticsEvent::query()->where('outbox_event_id', $event->id)->count())->toBe(1);
    });
});
