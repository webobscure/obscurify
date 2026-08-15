<?php

namespace App\Domain\CustomerIntelligence\Application;

use App\Domain\CustomerIntelligence\Enums\CustomerGroupType;
use App\Domain\CustomerIntelligence\Enums\SegmentableType;
use App\Domain\CustomerIntelligence\Exceptions\ProtectedCustomerGroupException;
use App\Domain\CustomerIntelligence\Models\CustomerGroup;
use App\Domain\CustomerIntelligence\Models\CustomerSegmentMembership;
use App\Domain\CustomerIntelligence\Models\SegmentRule;
use Illuminate\Support\Facades\DB;

final class DeleteCustomerGroup
{
    public function handle(CustomerGroup $group): void
    {
        if ($group->type === CustomerGroupType::Protected) {
            throw ProtectedCustomerGroupException::cannotDelete();
        }

        DB::transaction(function () use ($group) {
            // Manual groups' CustomerGroupMember rows cascade via a real
            // FK; a dynamic group's CustomerSegmentMembership cache rows
            // are polymorphic and can't, so they're cleaned up here —
            // see DeleteCustomerSegment's identical note.
            CustomerSegmentMembership::query()
                ->where('segmentable_type', SegmentableType::CustomerGroup->value)
                ->where('segmentable_id', $group->id)
                ->delete();

            // Only top-level rows need deleting explicitly — SegmentRule's
            // self-referencing parent_id FK cascades to every descendant.
            SegmentRule::query()
                ->where('segmentable_type', SegmentableType::CustomerGroup->value)
                ->where('segmentable_id', $group->id)
                ->whereNull('parent_id')
                ->delete();

            $group->delete();
        });
    }
}
