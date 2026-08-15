<?php

namespace App\Domain\Analytics\Enums;

/**
 * How AnalyticsAggregator computes one day's AnalyticsSnapshot row for
 * a metric — see MetricCalculator.
 */
enum MetricCalculation: string
{
    /** Sum of AnalyticsEvent.amount for matching events that day. */
    case Sum = 'sum';
    /** Count of matching AnalyticsEvent rows that day. */
    case Count = 'count';
    /** Sum / count of another metric, e.g. average order value. */
    case Average = 'average';
    /** A formula over other metrics' snapshot values, e.g. net revenue - refunds. */
    case Derived = 'derived';
    /** A breakdown map, e.g. Top Products. */
    case Leaderboard = 'leaderboard';
    /** A point-in-time reading, not a flow — e.g. Inventory Value; computed by AnalyticsSnapshotBuilder directly, not from AnalyticsEvent. */
    case Gauge = 'gauge';
    /** Always null — no real data source exists yet (spec section 3: "Conversion placeholder"). */
    case Placeholder = 'placeholder';
}
