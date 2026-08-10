<?php

namespace App\Domain\Returns\Enums;

enum ReturnDisposition: string
{
    case Restock = 'restock';
    case Damaged = 'damaged';
    case Repair = 'repair';
    case Discard = 'discard';
    case ManualReview = 'manual_review';
}
