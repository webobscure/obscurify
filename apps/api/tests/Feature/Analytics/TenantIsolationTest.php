<?php

use App\Domain\Analytics\Application\RegisterBuiltInAnalyticsCatalog;
use App\Domain\Analytics\Support\AnalyticsProjector;
use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);
    app(RegisterBuiltInAnalyticsCatalog::class)->handle();
});

it('never lists, shows, or lets one store access another store\'s dashboards or widgets', function () {
    $dashboardA = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/analytics/dashboards', ['name' => 'Store A dash'], tenantHeader($this->storeA));
    $dashboardAId = $dashboardA->json('data.id');

    $listB = $this->actingAs($this->userB, 'sanctum')->getJson('/api/v1/analytics/dashboards', tenantHeader($this->storeB));
    $listB->assertOk()->assertJsonCount(0, 'data');

    $showB = $this->actingAs($this->userB, 'sanctum')->getJson("/api/v1/analytics/dashboards/{$dashboardAId}", tenantHeader($this->storeB));
    $showB->assertNotFound();

    $widgetB = $this->actingAs($this->userB, 'sanctum')->postJson("/api/v1/analytics/dashboards/{$dashboardAId}/widgets", [
        'type' => 'metric_card', 'title' => 'Sneaky', 'config' => [],
    ], tenantHeader($this->storeB));
    $widgetB->assertNotFound();
});

it('never lets one store\'s events/snapshots leak into another store\'s metrics', function () {
    app(TenantContext::class)->scope($this->storeA, function () {
        $customer = Customer::factory()->create();
        $order = Order::query()->create([
            'number' => 1, 'customer_id' => $customer->id, 'currency' => 'USD',
            'items_subtotal_amount' => 10000, 'shipping_amount' => 0, 'discount_amount' => 0, 'tax_amount' => 0,
            'total_amount' => 10000, 'order_status' => 'open', 'financial_status' => 'paid', 'fulfillment_status' => 'unfulfilled',
            'email' => $customer->email,
        ]);
        $event = app(RecordOutboxEvent::class)->handle('OrderPaymentConfirmed', 'Order', $order->id, ['order_id' => $order->id, 'store_id' => $this->storeA->id]);
        app(AnalyticsProjector::class)->project($event);
    });

    $revenueA = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/analytics/reports', ['report_type' => 'orders'], tenantHeader($this->storeA));
    expect($revenueA->json('data.row_count'))->toBe(0); // orders report keys off OrderCreated, not OrderPaymentConfirmed

    $reportB = $this->actingAs($this->userB, 'sanctum')->postJson('/api/v1/analytics/reports', ['report_type' => 'payments'], tenantHeader($this->storeB));
    expect($reportB->json('data.row_count'))->toBe(0);

    $reportA = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/analytics/reports', ['report_type' => 'payments'], tenantHeader($this->storeA));
    expect($reportA->json('data.row_count'))->toBe(1);
});

it('never lets one store access another store\'s reports or report exports', function () {
    $reportA = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/analytics/reports', ['report_type' => 'orders'], tenantHeader($this->storeA));
    $reportAId = $reportA->json('data.id');

    $showB = $this->actingAs($this->userB, 'sanctum')->getJson("/api/v1/analytics/reports/{$reportAId}", tenantHeader($this->storeB));
    $showB->assertNotFound();

    $exportB = $this->actingAs($this->userB, 'sanctum')->postJson("/api/v1/analytics/reports/{$reportAId}/exports", ['format' => 'csv'], tenantHeader($this->storeB));
    $exportB->assertNotFound();

    $exportA = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/analytics/reports/{$reportAId}/exports", ['format' => 'csv'], tenantHeader($this->storeA));
    $exportA->assertCreated();

    $downloadB = $this->actingAs($this->userB, 'sanctum')->withHeaders(tenantHeader($this->storeB))->get("/api/v1/analytics/exports/{$exportA->json('data.id')}/download");
    $downloadB->assertNotFound();
});

it('the metric catalog is a global catalog visible identically to every store', function () {
    $listA = $this->actingAs($this->userA, 'sanctum')->getJson('/api/v1/analytics/metrics', tenantHeader($this->storeA));
    $listB = $this->actingAs($this->userB, 'sanctum')->getJson('/api/v1/analytics/metrics', tenantHeader($this->storeB));

    expect($listA->json('data'))->toHaveCount(19);
    expect(collect($listA->json('data'))->pluck('key')->sort()->values())
        ->toEqual(collect($listB->json('data'))->pluck('key')->sort()->values());
});
