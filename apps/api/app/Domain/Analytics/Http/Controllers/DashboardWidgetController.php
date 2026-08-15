<?php

namespace App\Domain\Analytics\Http\Controllers;

use App\Domain\Analytics\Application\CreateDashboardWidget;
use App\Domain\Analytics\Application\DeleteDashboardWidget;
use App\Domain\Analytics\Application\UpdateDashboardWidget;
use App\Domain\Analytics\Enums\TimeDimension;
use App\Domain\Analytics\Http\Requests\StoreDashboardWidgetRequest;
use App\Domain\Analytics\Http\Requests\UpdateDashboardWidgetRequest;
use App\Domain\Analytics\Http\Requests\WidgetDataRequest;
use App\Domain\Analytics\Http\Resources\DashboardWidgetResource;
use App\Domain\Analytics\Models\Dashboard;
use App\Domain\Analytics\Models\DashboardWidget;
use App\Domain\Analytics\Support\WidgetDataResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

final class DashboardWidgetController extends Controller
{
    public function __construct(private readonly WidgetDataResolver $dataResolver) {}

    public function index(Dashboard $dashboard): AnonymousResourceCollection
    {
        return DashboardWidgetResource::collection($dashboard->widgets);
    }

    /**
     * GET /analytics/widgets (spec section 11, flat) — every widget
     * across every dashboard, optionally narrowed to one via
     * ?dashboard_id=.
     */
    public function all(Request $request): AnonymousResourceCollection
    {
        $query = DashboardWidget::query()->orderBy('position');

        if ($request->filled('dashboard_id')) {
            $query->where('dashboard_id', $request->string('dashboard_id')->toString());
        }

        return DashboardWidgetResource::collection($query->get());
    }

    public function store(StoreDashboardWidgetRequest $request, Dashboard $dashboard, CreateDashboardWidget $action): JsonResponse
    {
        $widget = $action->handle($dashboard, $request->validated());

        return (new DashboardWidgetResource($widget))->response()->setStatusCode(201);
    }

    public function update(UpdateDashboardWidgetRequest $request, DashboardWidget $widget, UpdateDashboardWidget $action): DashboardWidgetResource
    {
        return new DashboardWidgetResource($action->handle($widget, $request->validated()));
    }

    public function destroy(DashboardWidget $widget, DeleteDashboardWidget $action): Response
    {
        $action->handle($widget);

        return response()->noContent();
    }

    /**
     * The computed data behind one widget, resolved from its own
     * `config` (metric_key + default time_dimension) unless the request
     * overrides the time range — e.g. changing a dashboard-wide date
     * filter without editing every widget's saved config.
     */
    public function data(WidgetDataRequest $request, DashboardWidget $widget): JsonResponse
    {
        $metricKey = $widget->config['metric_key'] ?? null;

        if ($metricKey === null) {
            return response()->json(['data' => null]);
        }

        $dimension = $this->resolveDimension($request, $widget);
        [$from, $to] = $this->resolveCustomRange($request);

        return response()->json(['data' => $this->dataResolver->resolve($metricKey, $dimension, $from, $to)]);
    }

    /**
     * Spec section 7: "Merchant can open details from every dashboard
     * widget."
     */
    public function drillDown(WidgetDataRequest $request, DashboardWidget $widget): JsonResponse
    {
        $metricKey = $widget->config['metric_key'] ?? null;

        if ($metricKey === null) {
            return response()->json(['data' => []]);
        }

        $dimension = $this->resolveDimension($request, $widget);
        [$from, $to] = $this->resolveCustomRange($request);

        // paginate() resolves the current page from the request's own
        // `?page=` query param automatically — nothing to pass through
        // explicitly here.
        return response()->json($this->dataResolver->drillDown($metricKey, $dimension, $from, $to));
    }

    private function resolveDimension(WidgetDataRequest $request, DashboardWidget $widget): TimeDimension
    {
        $requested = $request->validated('time_dimension');

        if ($requested !== null) {
            return TimeDimension::from($requested);
        }

        $configured = $widget->config['time_dimension'] ?? null;

        return $configured !== null ? (TimeDimension::tryFrom($configured) ?? TimeDimension::Last30Days) : TimeDimension::Last30Days;
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function resolveCustomRange(WidgetDataRequest $request): array
    {
        $from = $request->validated('from');
        $to = $request->validated('to');

        return [$from !== null ? Carbon::parse($from) : null, $to !== null ? Carbon::parse($to) : null];
    }
}
