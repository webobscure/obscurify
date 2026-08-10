<?php

namespace App\Domain\Returns\Enums;

enum ReturnReason: string
{
    case WrongSize = 'wrong_size';
    case Damaged = 'damaged';
    case NotAsDescribed = 'not_as_described';
    case OrderedByMistake = 'ordered_by_mistake';
    case Defective = 'defective';
    case Other = 'other';
}
