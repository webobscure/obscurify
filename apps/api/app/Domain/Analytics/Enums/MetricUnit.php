<?php

namespace App\Domain\Analytics\Enums;

enum MetricUnit: string
{
    case Currency = 'currency';
    case Count = 'count';
    case Percentage = 'percentage';
    case Ratio = 'ratio';
}
