<?php

namespace App\Domain\Notifications\Http\Controllers;

use App\Domain\Notifications\Application\CreateNotificationProvider;
use App\Domain\Notifications\Application\DeleteNotificationProvider;
use App\Domain\Notifications\Application\EnsureDefaultNotificationSetup;
use App\Domain\Notifications\Application\UpdateNotificationProvider;
use App\Domain\Notifications\Http\Requests\StoreNotificationProviderRequest;
use App\Domain\Notifications\Http\Requests\UpdateNotificationProviderRequest;
use App\Domain\Notifications\Http\Resources\NotificationProviderResource;
use App\Domain\Notifications\Models\NotificationProvider;
use App\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class NotificationProviderController extends Controller
{
    public function index(TenantContext $tenantContext, EnsureDefaultNotificationSetup $ensureDefaults): AnonymousResourceCollection
    {
        $ensureDefaults->handle($tenantContext->store());

        return NotificationProviderResource::collection(NotificationProvider::query()->orderBy('name')->get());
    }

    public function store(StoreNotificationProviderRequest $request, CreateNotificationProvider $action): JsonResponse
    {
        $provider = $action->handle($request->validated());

        return (new NotificationProviderResource($provider))->response()->setStatusCode(201);
    }

    public function update(UpdateNotificationProviderRequest $request, NotificationProvider $notificationProvider, UpdateNotificationProvider $action): NotificationProviderResource
    {
        return new NotificationProviderResource($action->handle($notificationProvider, $request->validated()));
    }

    public function destroy(NotificationProvider $notificationProvider, DeleteNotificationProvider $action): Response
    {
        $action->handle($notificationProvider);

        return response()->noContent();
    }
}
