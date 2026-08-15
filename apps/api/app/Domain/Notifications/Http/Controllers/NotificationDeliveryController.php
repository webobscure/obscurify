<?php

namespace App\Domain\Notifications\Http\Controllers;

use App\Domain\Notifications\Application\RetryNotificationDelivery;
use App\Domain\Notifications\Http\Resources\NotificationDeliveryResource;
use App\Domain\Notifications\Models\NotificationDelivery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Backs the admin "Delivery Log" / "Failed Deliveries" / "Retry Queue"
 * views (spec section 9) — all three are the same list, filtered by
 * `?status=`.
 */
final class NotificationDeliveryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = NotificationDelivery::query()->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return NotificationDeliveryResource::collection($query->paginate((int) $request->integer('per_page', 25)));
    }

    public function retry(NotificationDelivery $notificationDelivery, RetryNotificationDelivery $action): NotificationDeliveryResource
    {
        return new NotificationDeliveryResource($action->handle($notificationDelivery));
    }
}
