<?php

use App\Domain\Analytics\Support\AnalyticsProjector;
use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

it('runs an Orders report over real projected order data', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();
        $order = Order::query()->create([
            'number' => 1, 'customer_id' => $customer->id, 'currency' => 'USD',
            'items_subtotal_amount' => 5000, 'shipping_amount' => 0, 'discount_amount' => 0, 'tax_amount' => 0,
            'total_amount' => 5000, 'order_status' => 'open', 'financial_status' => 'paid', 'fulfillment_status' => 'unfulfilled',
            'email' => $customer->email,
        ]);
        $event = app(RecordOutboxEvent::class)->handle('OrderCreated', 'Order', $order->id, ['order_id' => $order->id, 'store_id' => $this->store->id]);
        app(AnalyticsProjector::class)->project($event);
    });

    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/analytics/reports', [
        'report_type' => 'orders',
        'filters' => ['from' => now()->subDay()->toDateString(), 'to' => now()->toDateString()],
    ], tenantHeader($this->store));

    $response->assertCreated()->assertJsonPath('data.status', 'completed');
    expect($response->json('data.row_count'))->toBe(1);
    expect($response->json('data.result.0.amount'))->toBe(5000);
});

it('every report type runs without error, even with zero matching data', function (string $reportType) {
    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/analytics/reports', [
        'report_type' => $reportType,
    ], tenantHeader($this->store));

    $response->assertCreated()->assertJsonPath('data.status', 'completed');
    expect($response->json('data.row_count'))->toBe(0);
})->with([
    'orders', 'products', 'customers', 'inventory', 'shipping', 'payments', 'returns', 'promotions', 'automation_executions',
]);

it('rejects an unknown report_type', function () {
    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/analytics/reports', [
        'report_type' => 'not_a_real_type',
    ], tenantHeader($this->store));

    $response->assertUnprocessable();
});

it('saves a report configuration and reuses it to run a report', function () {
    $saved = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/analytics/saved-reports', [
        'name' => 'Weekly Orders',
        'report_type' => 'orders',
        'filters' => ['from' => now()->subDays(7)->toDateString(), 'to' => now()->toDateString()],
    ], tenantHeader($this->store));
    $saved->assertCreated();
    $savedReportId = $saved->json('data.id');

    $report = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/analytics/reports', [
        'report_type' => 'orders',
        'saved_report_id' => $savedReportId,
    ], tenantHeader($this->store));
    $report->assertCreated()->assertJsonPath('data.saved_report_id', $savedReportId);

    $updated = $this->actingAs($this->user, 'sanctum')->patchJson("/api/v1/analytics/saved-reports/{$savedReportId}", ['name' => 'Weekly Orders v2'], tenantHeader($this->store));
    $updated->assertOk()->assertJsonPath('data.name', 'Weekly Orders v2');

    $this->actingAs($this->user, 'sanctum')->deleteJson("/api/v1/analytics/saved-reports/{$savedReportId}", [], tenantHeader($this->store))->assertNoContent();
});
