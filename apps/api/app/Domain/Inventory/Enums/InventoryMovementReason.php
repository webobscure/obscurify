<?php

namespace App\Domain\Inventory\Enums;

enum InventoryMovementReason: string
{
    case ManualAdjustment = 'manual_adjustment';
    case InitialStock = 'initial_stock';
    case Import = 'import';
    case ReturnStock = 'return';
    case Damage = 'damage';
    case Correction = 'correction';
}
