<?php

namespace App\Domain\Analytics\Enums;

enum ReportType: string
{
    case Orders = 'orders';
    case Products = 'products';
    case Customers = 'customers';
    case Inventory = 'inventory';
    case Shipping = 'shipping';
    case Payments = 'payments';
    case Returns = 'returns';
    case Promotions = 'promotions';
    case AutomationExecutions = 'automation_executions';
}
