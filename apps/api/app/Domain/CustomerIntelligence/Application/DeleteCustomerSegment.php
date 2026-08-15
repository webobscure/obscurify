<?php

namespace App\Domain\CustomerIntelligence\Application;

use App\Domain\CustomerIntelligence\Enums\SegmentableType;
use App\Domain\CustomerIntelligence\Models\CustomerSegment;
use App\Domain\CustomerIntelligence\Models\CustomerSegmentMembership;
use App\Domain\CustomerIntelligence\Models\SegmentRule;
use Illuminate\Support\Facades\DB;

/**
 * segment_rules cascades on delete via its own FK, but
 * customer_segment_memberships is polymorphic (segmentable_type/
 * segmentable_id) and can't carry a real FK — cleaned up here instead,
 * or deleting a segment would leave orphaned "member of a segment that
 * no longer exists" rows behind.
 */
final class DeleteCustomerSegment
{
    public function handle(CustomerSegment $segment): void
    {
        DB::transaction(function () use ($segment) {
            CustomerSegmentMembership::query()
                ->where('segmentable_type', SegmentableType::CustomerSegment->value)
                ->where('segmentable_id', $segment->id)
                ->delete();

            // Only top-level rows need deleting explicitly — SegmentRule's
            // self-referencing parent_id FK cascades to every descendant.
            SegmentRule::query()
                ->where('segmentable_type', SegmentableType::CustomerSegment->value)
                ->where('segmentable_id', $segment->id)
                ->whereNull('parent_id')
                ->delete();

            $segment->delete();
        });
    }
}
