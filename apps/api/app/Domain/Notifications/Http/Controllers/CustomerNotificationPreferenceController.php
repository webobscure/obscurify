<?php

namespace App\Domain\Notifications\Http\Controllers;

use App\Domain\Customers\Support\CurrentCustomerContext;
use App\Domain\Notifications\Application\UpdateNotificationPreference;
use App\Domain\Notifications\Http\Requests\UpdateNotificationPreferenceRequest;
use App\Domain\Notifications\Http\Resources\NotificationPreferenceResource;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Http\Controllers\Controller;

final class CustomerNotificationPreferenceController extends Controller
{
    public function show(CurrentCustomerContext $currentCustomerContext): NotificationPreferenceResource
    {
        $customer = $currentCustomerContext->customer();
        $preference = NotificationPreference::query()->where('customer_id', $customer->id)->first()
            ?? new NotificationPreference(['customer_id' => $customer->id]);

        return new NotificationPreferenceResource($preference);
    }

    public function update(UpdateNotificationPreferenceRequest $request, CurrentCustomerContext $currentCustomerContext, UpdateNotificationPreference $action): NotificationPreferenceResource
    {
        return new NotificationPreferenceResource($action->handle($currentCustomerContext->customer(), $request->validated()));
    }
}
