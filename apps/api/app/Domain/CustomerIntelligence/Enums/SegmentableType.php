<?php

namespace App\Domain\CustomerIntelligence\Enums;

/**
 * The `segment_rules.segmentable_type` discriminator — deliberately
 * fixed string values rather than Eloquent's morph-map class names, so
 * the column never depends on a namespace surviving a future rename.
 */
enum SegmentableType: string
{
    case CustomerGroup = 'CustomerGroup';
    case CustomerSegment = 'CustomerSegment';
}
