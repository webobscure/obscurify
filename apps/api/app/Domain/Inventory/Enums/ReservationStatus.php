<?php

namespace App\Domain\Inventory\Enums;

enum ReservationStatus: string
{
    case Active = 'active';
    case Consumed = 'consumed';
    case Released = 'released';
    case Expired = 'expired';
}
