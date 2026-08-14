<?php

namespace App\Domain\Apps\Http\Controllers;

use App\Domain\Apps\Application\InstallApp;
use App\Domain\Apps\Application\RegisterApp;
use App\Domain\Apps\Http\Requests\StoreAppRequest;
use App\Domain\Apps\Http\Resources\AppResource;
use App\Domain\Apps\Http\Resources\InstalledAppResource;
use App\Domain\Apps\Models\App;
use App\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Apps visible to a store: the ones it registered itself (Private) plus
 * every Public app (spec section 2: "internal support only" — no
 * marketplace browsing UI, but an admin who already knows a public
 * app exists can still see and install it here).
 */
final class AppController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function index(): AnonymousResourceCollection
    {
        $storeId = $this->tenantContext->storeId();

        $apps = App::query()
            ->where(fn ($query) => $query->where('store_id', $storeId)->orWhereNull('store_id'))
            ->with('oauthClient')
            ->orderByDesc('created_at')
            ->get();

        return AppResource::collection($apps);
    }

    public function show(App $app): AppResource
    {
        $this->assertVisible($app);

        return new AppResource($app->load('oauthClient'));
    }

    public function store(StoreAppRequest $request, RegisterApp $action): JsonResponse
    {
        $result = $action->handle($request->validated(), $this->tenantContext->storeId());

        $body = (new AppResource($result['app']->load('oauthClient')))->resolve();
        $body['client_secret'] = $result['client_secret'];

        return response()->json(['data' => $body], 201);
    }

    public function install(App $app, InstallApp $action): InstalledAppResource
    {
        $this->assertVisible($app);

        $installedApp = $action->handle($app);

        return new InstalledAppResource($installedApp->load(['app', 'permissions']));
    }

    private function assertVisible(App $app): void
    {
        if ($app->store_id !== null && $app->store_id !== $this->tenantContext->storeId()) {
            abort(404);
        }
    }
}
