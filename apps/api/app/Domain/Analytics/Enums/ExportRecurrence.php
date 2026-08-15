<?php

namespace App\Domain\Analytics\Enums;

/**
 * Spec section 9: "Scheduled export architecture only" — this enum and
 * ReportExport.scheduled_at/recurrence describe the intent; nothing in
 * this milestone dispatches a recurring export automatically.
 */
enum ExportRecurrence: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
}
