<?php

namespace App\Domain\Financial\Enums;

enum LedgerDirection: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
