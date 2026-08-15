<?php

namespace App\Domain\Notifications\Http\Controllers;

use App\Domain\Customers\Models\Customer;
use App\Domain\Notifications\Application\UpdateNotificationPreference;
use App\Domain\Notifications\Http\Requests\UpdateNotificationPreferenceRequest;
use App\Domain\Notifications\Http\Resources\NotificationPreferenceResource;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Http\Controllers\Controller;

/**
 * The admin-on-behalf-of-a-customer counterpart to
 * CustomerNotificationPreferenceController (self-service, under
 * `/account`). Spec section 11's `PATCH /notification-preferences` is
 * the customer-self route; an admin necessarily needs a customer id in
 * the path, since one merchant session manages many customers'
 * preferences.
 */
final class AdminNotificationPreferenceController extends Controller
{
    public function show(Customer $customer): NotificationPreferenceResource
    {
        $preference = NotificationPreference::query()->where('customer_id', $customer->id)->first()
            ?? new NotificationPreference(['customer_id' => $customer->id]);

        return new NotificationPreferenceResource($preference);
    }

    public function update(UpdateNotificationPreferenceRequest $request, Customer $customer, UpdateNotificationPreference $action): NotificationPreferenceResource
    {
        return new NotificationPreferenceResource($action->handle($customer, $request->validated()));
    }
}
