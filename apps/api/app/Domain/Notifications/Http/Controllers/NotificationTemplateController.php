<?php

namespace App\Domain\Notifications\Http\Controllers;

use App\Domain\Notifications\Application\CreateNotificationTemplate;
use App\Domain\Notifications\Application\DeleteNotificationTemplate;
use App\Domain\Notifications\Application\UpdateNotificationTemplate;
use App\Domain\Notifications\Http\Requests\StoreNotificationTemplateRequest;
use App\Domain\Notifications\Http\Requests\UpdateNotificationTemplateRequest;
use App\Domain\Notifications\Http\Resources\NotificationTemplateResource;
use App\Domain\Notifications\Models\NotificationTemplate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class NotificationTemplateController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return NotificationTemplateResource::collection(NotificationTemplate::query()->orderBy('name')->get());
    }

    public function store(StoreNotificationTemplateRequest $request, CreateNotificationTemplate $action): JsonResponse
    {
        $template = $action->handle($request->validated());

        return (new NotificationTemplateResource($template))->response()->setStatusCode(201);
    }

    public function show(NotificationTemplate $notificationTemplate): NotificationTemplateResource
    {
        return new NotificationTemplateResource($notificationTemplate);
    }

    public function update(UpdateNotificationTemplateRequest $request, NotificationTemplate $notificationTemplate, UpdateNotificationTemplate $action): NotificationTemplateResource
    {
        return new NotificationTemplateResource($action->handle($notificationTemplate, $request->validated()));
    }

    public function destroy(NotificationTemplate $notificationTemplate, DeleteNotificationTemplate $action): Response
    {
        $action->handle($notificationTemplate);

        return response()->noContent();
    }
}
