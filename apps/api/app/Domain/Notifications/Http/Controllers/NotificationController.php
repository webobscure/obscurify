<?php

namespace App\Domain\Notifications\Http\Controllers;

use App\Domain\Notifications\Application\SendNotification;
use App\Domain\Notifications\Http\Requests\StoreNotificationRequest;
use App\Domain\Notifications\Http\Resources\NotificationResource;
use App\Domain\Notifications\Http\Resources\NotificationSummaryResource;
use App\Domain\Notifications\Models\Notification;
use App\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class NotificationController extends Controller
{
    /**
     * The Notification Center's main list (spec section 9) —
     * `?status=`/`?channel=` narrow it to what a "Delivery Log"-style
     * view needs.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Notification::query()->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->string('channel')->toString());
        }

        return NotificationSummaryResource::collection($query->paginate((int) $request->integer('per_page', 25)));
    }

    public function store(StoreNotificationRequest $request, TenantContext $tenantContext, SendNotification $action): JsonResponse
    {
        $notification = $action->handle($tenantContext->store(), $request->validated());

        return (new NotificationResource($notification))->response()->setStatusCode(201);
    }

    public function show(Notification $notification): NotificationResource
    {
        return new NotificationResource($notification->load(['recipients', 'deliveries']));
    }
}
