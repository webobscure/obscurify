<?php

namespace App\Domain\Apps\Http\Controllers;

use App\Domain\Apps\Application\UninstallApp;
use App\Domain\Apps\Http\Resources\AppTokenResource;
use App\Domain\Apps\Http\Resources\InstalledAppResource;
use App\Domain\Apps\Models\InstalledApp;
use App\Domain\Webhooks\Http\Resources\WebhookSubscriptionResource;
use App\Domain\Webhooks\Models\WebhookSubscription;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class InstalledAppController extends Controller
{
    private const array WITH = ['app', 'permissions'];

    public function index(): AnonymousResourceCollection
    {
        $installedApps = InstalledApp::query()->with(self::WITH)->orderByDesc('created_at')->get();

        return InstalledAppResource::collection($installedApps);
    }

    public function show(InstalledApp $installedApp): InstalledAppResource
    {
        return new InstalledAppResource($installedApp->load(self::WITH));
    }

    public function uninstall(InstalledApp $installedApp, UninstallApp $action): InstalledAppResource
    {
        $installedApp = $action->handle($installedApp);

        return new InstalledAppResource($installedApp->load(self::WITH));
    }

    /**
     * Read-only, for audit (spec section 12) — a token can never be
     * re-created here, only issued/refreshed through the OAuth flow
     * itself.
     */
    public function tokens(InstalledApp $installedApp): AnonymousResourceCollection
    {
        $tokens = $installedApp->tokens()->orderByDesc('created_at')->get();

        return AppTokenResource::collection($tokens);
    }

    /**
     * The app's own webhook subscriptions (spec section 13) — rows it
     * created itself via `/api/apps/v1/webhooks`, reusing Milestone 11's
     * WebhookSubscription engine with `owner_type = 'app'`.
     */
    public function webhooks(InstalledApp $installedApp): AnonymousResourceCollection
    {
        $subscriptions = WebhookSubscription::query()
            ->where('owner_type', 'app')
            ->where('owner_id', $installedApp->id)
            ->orderByDesc('created_at')
            ->get();

        return WebhookSubscriptionResource::collection($subscriptions);
    }
}
