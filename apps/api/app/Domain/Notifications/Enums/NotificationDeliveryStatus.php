<?php

namespace App\Domain\Notifications\Enums;

/**
 * Mirrors WebhookDeliveryStatus exactly (Pending/Succeeded/Failed/
 * Exhausted), plus Suppressed for a customer-preference gate: a
 * delivery that was never attempted because the recipient has that
 * channel disabled (NotificationPreference) — a terminal state, but
 * distinct from a genuine send failure.
 */
enum NotificationDeliveryStatus: string
{
    case Pending = 'pending';
    /**
     * A transient claim marker (SendNotificationDeliveryJob's guarded
     * UPDATE), the same role WorkflowExecutionStatus::Running plays for
     * WorkflowExecution — never a status a caller sets directly.
     */
    case Sending = 'sending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Exhausted = 'exhausted';
    case Suppressed = 'suppressed';
}
