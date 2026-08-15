<?php

namespace App\Domain\Analytics\Enums;

enum DashboardWidgetType: string
{
    case LineChart = 'line_chart';
    case BarChart = 'bar_chart';
    case PieChart = 'pie_chart';
    case MetricCard = 'metric_card';
    case Table = 'table';
    case Leaderboard = 'leaderboard';
}
