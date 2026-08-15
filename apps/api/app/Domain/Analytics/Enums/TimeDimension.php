<?php

namespace App\Domain\Analytics\Enums;

/**
 * Spec section 4. `Custom` requires an explicit `{from, to}` pair
 * supplied alongside it — see TimeRangeResolver.
 */
enum TimeDimension: string
{
    case Today = 'today';
    case Yesterday = 'yesterday';
    case Last7Days = 'last_7_days';
    case Last30Days = 'last_30_days';
    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';
    case Custom = 'custom';
}
