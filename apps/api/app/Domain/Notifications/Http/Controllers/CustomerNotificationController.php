<?php

namespace App\Domain\Notifications\Http\Controllers;

use App\Domain\Customers\Support\CurrentCustomerContext;
use App\Domain\Notifications\Application\MarkNotificationRead;
use App\Domain\Notifications\Http\Resources\NotificationRecipientResource;
use App\Domain\Notifications\Models\NotificationRecipient;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * `/account/notifications` (spec section 10) — history is read through
 * NotificationRecipient (one row per customer per notification), not
 * Notification directly, since read/unread state lives there. Eager-
 * loads `notification` so the portal can render subject/body without a
 * second round trip per row.
 */
final class CustomerNotificationController extends Controller
{
    public function index(Request $request, CurrentCustomerContext $currentCustomerContext): AnonymousResourceCollection
    {
        $recipients = NotificationRecipient::query()
            ->where('customer_id', $currentCustomerContext->customer()->id)
            ->with('notification')
            ->orderByDesc('created_at')
            ->paginate((int) $request->integer('per_page', 20));

        return NotificationRecipientResource::collection($recipients);
    }

    public function markRead(NotificationRecipient $notificationRecipient, CurrentCustomerContext $currentCustomerContext, MarkNotificationRead $action): NotificationRecipientResource
    {
        if ($notificationRecipient->customer_id !== $currentCustomerContext->customer()->id) {
            throw new AuthorizationException('This notification does not belong to you.');
        }

        return new NotificationRecipientResource($action->handle($notificationRecipient));
    }
}
