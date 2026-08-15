<?php

namespace App\Domain\Analytics\Enums;

enum MetricCategory: string
{
    case Revenue = 'revenue';
    case Orders = 'orders';
    case Customers = 'customers';
    case Inventory = 'inventory';
    case Leaderboard = 'leaderboard';
}
