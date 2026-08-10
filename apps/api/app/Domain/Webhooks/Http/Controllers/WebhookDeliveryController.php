<?php

namespace App\Domain\Webhooks\Http\Controllers;

use App\Domain\Webhooks\Application\RetryWebhookDelivery;
use App\Domain\Webhooks\Http\Resources\WebhookDeliveryResource;
use App\Domain\Webhooks\Models\WebhookDelivery;
use App\Domain\Webhooks\Models\WebhookSubscription;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class WebhookDeliveryController extends Controller
{
    public function index(WebhookSubscription $webhookSubscription): AnonymousResourceCollection
    {
        $deliveries = $webhookSubscription->deliveries()->orderByDesc('created_at')->paginate(50);

        return WebhookDeliveryResource::collection($deliveries);
    }

    public function retry(WebhookDelivery $webhookDelivery, RetryWebhookDelivery $action): WebhookDeliveryResource
    {
        $delivery = $action->handle($webhookDelivery);

        return new WebhookDeliveryResource($delivery);
    }
}
