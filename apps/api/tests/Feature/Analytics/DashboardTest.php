<?php

use App\Domain\Analytics\Application\RegisterBuiltInAnalyticsCatalog;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    app(RegisterBuiltInAnalyticsCatalog::class)->handle();
});

it('auto-creates and returns a default dashboard on first access', function () {
    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/analytics/dashboard', tenantHeader($this->store));

    $response->assertOk()->assertJsonPath('data.is_default', true);

    // Calling it again returns the SAME dashboard, not a second one.
    $second = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/analytics/dashboard', tenantHeader($this->store));
    expect($second->json('data.id'))->toBe($response->json('data.id'));

    $list = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/analytics/dashboards', tenantHeader($this->store));
    $list->assertOk()->assertJsonCount(1, 'data');
});

it('creates a dashboard, adds widgets of each type, and lists them nested and flat', function () {
    $created = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/analytics/dashboards', ['name' => 'Sales'], tenantHeader($this->store));
    $created->assertCreated();
    $dashboardId = $created->json('data.id');

    foreach (['line_chart', 'bar_chart', 'pie_chart', 'metric_card', 'table', 'leaderboard'] as $type) {
        $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/analytics/dashboards/{$dashboardId}/widgets", [
            'type' => $type,
            'title' => ucfirst($type),
            'config' => ['metric_key' => 'net_revenue'],
        ], tenantHeader($this->store))->assertCreated();
    }

    $nested = $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/analytics/dashboards/{$dashboardId}/widgets", tenantHeader($this->store));
    $nested->assertOk()->assertJsonCount(6, 'data');

    $flat = $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/analytics/widgets?dashboard_id={$dashboardId}", tenantHeader($this->store));
    $flat->assertOk()->assertJsonCount(6, 'data');
});

it('fetches a widget\'s computed data and drill-down', function () {
    $dashboard = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/analytics/dashboards', ['name' => 'Sales'], tenantHeader($this->store));
    $widget = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/analytics/dashboards/{$dashboard->json('data.id')}/widgets", [
        'type' => 'metric_card', 'title' => 'Revenue', 'config' => ['metric_key' => 'net_revenue', 'time_dimension' => 'last_7_days'],
    ], tenantHeader($this->store));
    $widgetId = $widget->json('data.id');

    $data = $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/analytics/widgets/{$widgetId}/data", tenantHeader($this->store));
    $data->assertOk()->assertJsonPath('data.metric_key', 'net_revenue');
    expect($data->json('data.total'))->toBe(0);

    $drillDown = $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/analytics/widgets/{$widgetId}/drill-down", tenantHeader($this->store));
    $drillDown->assertOk();

    // A time_dimension override in the query string takes precedence
    // over the widget's own saved config.
    $overridden = $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/analytics/widgets/{$widgetId}/data?time_dimension=today", tenantHeader($this->store));
    $overridden->assertOk();
});

it('updates and deletes a widget', function () {
    $dashboard = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/analytics/dashboards', ['name' => 'Sales'], tenantHeader($this->store));
    $widget = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/analytics/dashboards/{$dashboard->json('data.id')}/widgets", [
        'type' => 'metric_card', 'title' => 'Revenue', 'config' => ['metric_key' => 'net_revenue'],
    ], tenantHeader($this->store));
    $widgetId = $widget->json('data.id');

    $updated = $this->actingAs($this->user, 'sanctum')->patchJson("/api/v1/analytics/widgets/{$widgetId}", ['title' => 'Total Revenue'], tenantHeader($this->store));
    $updated->assertOk()->assertJsonPath('data.title', 'Total Revenue');

    $this->actingAs($this->user, 'sanctum')->deleteJson("/api/v1/analytics/widgets/{$widgetId}", [], tenantHeader($this->store))->assertNoContent();

    $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/analytics/widgets/{$widgetId}/data", tenantHeader($this->store))->assertNotFound();
});

it('lists the metric catalog', function () {
    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/analytics/metrics', tenantHeader($this->store));

    $response->assertOk()->assertJsonCount(22, 'data');
    expect(collect($response->json('data'))->pluck('key'))->toContain('gross_revenue', 'top_products', 'inventory_value');
});
