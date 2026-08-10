<?php

namespace App\Domain\Webhooks\Http\Controllers;

use App\Domain\Webhooks\Application\CreateWebhookSubscription;
use App\Domain\Webhooks\Application\UpdateWebhookSubscription;
use App\Domain\Webhooks\Http\Requests\StoreWebhookSubscriptionRequest;
use App\Domain\Webhooks\Http\Requests\UpdateWebhookSubscriptionRequest;
use App\Domain\Webhooks\Http\Resources\WebhookSubscriptionResource;
use App\Domain\Webhooks\Models\WebhookSubscription;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class WebhookSubscriptionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $subscriptions = WebhookSubscription::query()->where('owner_type', 'store')->orderByDesc('created_at')->get();

        return WebhookSubscriptionResource::collection($subscriptions);
    }

    public function show(WebhookSubscription $webhookSubscription): WebhookSubscriptionResource
    {
        return new WebhookSubscriptionResource($webhookSubscription);
    }

    /**
     * The only response that ever includes `secret` — generated here,
     * shown once, unrecoverable afterward (spec: "Never store plaintext
     * secrets" in responses past this point; it is still stored
     * encrypted at rest — see WebhookSubscription::casts()).
     */
    public function store(StoreWebhookSubscriptionRequest $request, CreateWebhookSubscription $action): JsonResponse
    {
        $subscription = $action->handle($request->validated());

        $data = (new WebhookSubscriptionResource($subscription))->resolve();
        $data['secret'] = $subscription->secret;

        return response()->json(['data' => $data], 201);
    }

    public function update(UpdateWebhookSubscriptionRequest $request, WebhookSubscription $webhookSubscription, UpdateWebhookSubscription $action): WebhookSubscriptionResource
    {
        $subscription = $action->handle($webhookSubscription, $request->validated());

        return new WebhookSubscriptionResource($subscription);
    }
}
