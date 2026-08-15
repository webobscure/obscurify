<?php

namespace App\Domain\Search\Enums;

enum SearchIndexStatus: string
{
    case Building = 'building';
    case Ready = 'ready';
    case Stale = 'stale';
    case Failed = 'failed';
}
