<?php

namespace App\Domain\Notifications\Http\Controllers;

use App\Domain\Notifications\Application\CreateNotificationEvent;
use App\Domain\Notifications\Application\DeleteNotificationEvent;
use App\Domain\Notifications\Application\UpdateNotificationEvent;
use App\Domain\Notifications\Http\Requests\StoreNotificationEventRequest;
use App\Domain\Notifications\Http\Requests\UpdateNotificationEventRequest;
use App\Domain\Notifications\Http\Resources\NotificationEventResource;
use App\Domain\Notifications\Models\NotificationEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class NotificationEventController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return NotificationEventResource::collection(NotificationEvent::query()->with('template')->orderBy('event_type')->get());
    }

    public function store(StoreNotificationEventRequest $request, CreateNotificationEvent $action): JsonResponse
    {
        $event = $action->handle($request->validated());

        return (new NotificationEventResource($event->load('template')))->response()->setStatusCode(201);
    }

    public function update(UpdateNotificationEventRequest $request, NotificationEvent $notificationEvent, UpdateNotificationEvent $action): NotificationEventResource
    {
        return new NotificationEventResource($action->handle($notificationEvent, $request->validated())->load('template'));
    }

    public function destroy(NotificationEvent $notificationEvent, DeleteNotificationEvent $action): Response
    {
        $action->handle($notificationEvent);

        return response()->noContent();
    }
}
