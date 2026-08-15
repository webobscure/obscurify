<?php

namespace App\Domain\Analytics\Support;

use App\Domain\Analytics\Enums\TimeDimension;
use Illuminate\Support\Carbon;

/**
 * Spec section 4 — converts a TimeDimension (+ an explicit {from, to}
 * pair for Custom) into a concrete date range every widget/report/
 * drill-down query resolves against.
 */
final class TimeRangeResolver
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolve(TimeDimension $dimension, ?Carbon $customFrom = null, ?Carbon $customTo = null): array
    {
        $now = now();

        return match ($dimension) {
            TimeDimension::Today => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            TimeDimension::Yesterday => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            TimeDimension::Last7Days => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            TimeDimension::Last30Days => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            TimeDimension::Month => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            TimeDimension::Quarter => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            TimeDimension::Year => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            TimeDimension::Custom => [
                ($customFrom ?? $now->copy()->startOfDay())->copy()->startOfDay(),
                ($customTo ?? $now->copy()->endOfDay())->copy()->endOfDay(),
            ],
        };
    }
}
