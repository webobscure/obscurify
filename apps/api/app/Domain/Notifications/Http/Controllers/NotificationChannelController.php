<?php

namespace App\Domain\Notifications\Http\Controllers;

use App\Domain\Notifications\Application\EnsureDefaultNotificationSetup;
use App\Domain\Notifications\Application\UpdateNotificationChannel;
use App\Domain\Notifications\Http\Requests\UpdateNotificationChannelRequest;
use App\Domain\Notifications\Http\Resources\NotificationChannelResource;
use App\Domain\Notifications\Models\NotificationChannel;
use App\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class NotificationChannelController extends Controller
{
    public function index(TenantContext $tenantContext, EnsureDefaultNotificationSetup $ensureDefaults): AnonymousResourceCollection
    {
        $ensureDefaults->handle($tenantContext->store());

        return NotificationChannelResource::collection(NotificationChannel::query()->with('provider')->orderBy('channel')->get());
    }

    public function update(UpdateNotificationChannelRequest $request, NotificationChannel $notificationChannel, UpdateNotificationChannel $action): NotificationChannelResource
    {
        return new NotificationChannelResource($action->handle($notificationChannel, $request->validated())->load('provider'));
    }
}
