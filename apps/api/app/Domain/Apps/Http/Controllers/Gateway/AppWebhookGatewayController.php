<?php

namespace App\Domain\Apps\Http\Controllers\Gateway;

use App\Domain\Apps\Support\CurrentAppContext;
use App\Domain\Webhooks\Enums\WebhookSubscriptionStatus;
use App\Domain\Webhooks\Http\Resources\WebhookSubscriptionResource;
use App\Domain\Webhooks\Models\WebhookSubscription;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/**
 * An app's self-service webhook subscriptions (`webhooks.read`/
 * `webhooks.write`) — spec section 6: "Apps subscribe through webhook
 * subscriptions... never subscribe directly to business domains." This
 * reuses Milestone 11's WebhookSubscription/DeliverWebhookJob engine
 * entirely (signing, retry, idempotency) via `owner_type = 'app'`,
 * `owner_id = installed_app_id` — no separate delivery mechanism exists
 * for app-owned subscriptions.
 */
final class AppWebhookGatewayController extends Controller
{
    public function __construct(private readonly CurrentAppContext $currentAppContext) {}

    public function index(): AnonymousResourceCollection
    {
        $installedApp = $this->currentAppContext->installedApp();

        $subscriptions = WebhookSubscription::query()
            ->where('owner_type', 'app')
            ->where('owner_id', $installedApp->id)
            ->orderByDesc('created_at')
            ->get();

        return WebhookSubscriptionResource::collection($subscriptions);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'target_url' => ['required', 'string', 'url', 'max:2048'],
            'event_types' => ['required', 'array', 'min:1'],
            'event_types.*' => ['required', 'string'],
        ]);

        $installedApp = $this->currentAppContext->installedApp();

        $subscription = WebhookSubscription::query()->create([
            'owner_type' => 'app',
            'owner_id' => $installedApp->id,
            'name' => $data['name'],
            'target_url' => $data['target_url'],
            'secret' => Str::random(48),
            'event_types' => $data['event_types'],
            'status' => WebhookSubscriptionStatus::Active->value,
        ]);

        $body = (new WebhookSubscriptionResource($subscription))->resolve();
        $body['secret'] = $subscription->secret;

        return response()->json(['data' => $body], 201);
    }
}
