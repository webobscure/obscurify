<?php

namespace App\Domain\Analytics\Http\Controllers;

use App\Domain\Analytics\Application\CreateDashboard;
use App\Domain\Analytics\Application\DeleteDashboard;
use App\Domain\Analytics\Application\UpdateDashboard;
use App\Domain\Analytics\Http\Requests\StoreDashboardRequest;
use App\Domain\Analytics\Http\Requests\UpdateDashboardRequest;
use App\Domain\Analytics\Http\Resources\DashboardResource;
use App\Domain\Analytics\Models\Dashboard;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class DashboardController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return DashboardResource::collection(Dashboard::query()->with('widgets')->orderByDesc('is_default')->orderBy('name')->get());
    }

    /**
     * GET /analytics/dashboard (spec section 11, singular — "the"
     * dashboard) — the store's default dashboard, auto-created on
     * first access so a merchant always has something to see rather
     * than an empty state with no path forward.
     */
    public function default(CreateDashboard $createDashboard): JsonResponse
    {
        $dashboard = Dashboard::query()->where('is_default', true)->first();

        if ($dashboard === null) {
            $dashboard = Dashboard::query()->first() ?? $createDashboard->handle(['name' => 'Overview', 'is_default' => true]);
        }

        // Explicit 200 even though $dashboard may have just been
        // created — JsonResource auto-sets 201 for a wasRecentlyCreated
        // model, which is the wrong signal for a GET endpoint whose
        // contract is "fetch the default dashboard," not "create one."
        return (new DashboardResource($dashboard->load('widgets')))->response()->setStatusCode(200);
    }

    public function store(StoreDashboardRequest $request, CreateDashboard $action): JsonResponse
    {
        $dashboard = $action->handle($request->validated());

        return (new DashboardResource($dashboard->load('widgets')))->response()->setStatusCode(201);
    }

    public function show(Dashboard $dashboard): DashboardResource
    {
        return new DashboardResource($dashboard->load('widgets'));
    }

    public function update(UpdateDashboardRequest $request, Dashboard $dashboard, UpdateDashboard $action): DashboardResource
    {
        return new DashboardResource($action->handle($dashboard, $request->validated())->load('widgets'));
    }

    public function destroy(Dashboard $dashboard, DeleteDashboard $action): Response
    {
        $action->handle($dashboard);

        return response()->noContent();
    }
}
