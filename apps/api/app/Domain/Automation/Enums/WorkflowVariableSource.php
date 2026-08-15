<?php

namespace App\Domain\Automation\Enums;

enum WorkflowVariableSource: string
{
    case Customer = 'customer';
    case Order = 'order';
    case Payment = 'payment';
    case Shipment = 'shipment';
    case ReturnRequest = 'return';
    case Inventory = 'inventory';
    case Store = 'store';
    case Trigger = 'trigger';
}
