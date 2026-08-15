<?php

namespace App\Domain\CustomerIntelligence\Application;

use App\Domain\CustomerIntelligence\Enums\CustomerGroupType;
use App\Domain\CustomerIntelligence\Enums\SegmentableType;
use App\Domain\CustomerIntelligence\Models\CustomerGroup;
use App\Domain\CustomerIntelligence\Models\CustomerGroupMember;
use App\Domain\CustomerIntelligence\Models\CustomerSegmentMembership;
use App\Domain\Customers\Models\Customer;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manual-group-only (spec section 2) — a dynamic/protected group's
 * membership is never something a merchant adds/removes directly, it's
 * always computed. Also mirrors the membership into
 * CustomerSegmentMembership and fires the same CustomerEnteredSegment
 * event a dynamic group entry would, so a Promotion/webhook subscriber
 * never needs to know or care whether a given group happened to be
 * manual or rule-computed.
 *
 * Locks the CustomerGroup row for the duration of the check+insert —
 * without it, two concurrent adds of the same customer to the same
 * group could both pass the "not already a member" check before either
 * commits, and the second INSERT would then hit `customer_group_members`'
 * unique(customer_group_id, customer_id) constraint as a hard failure
 * instead of the graceful no-op a duplicate add should be.
 */
final class AddCustomerToGroup
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    public function handle(CustomerGroup $group, Customer $customer): void
    {
        if ($group->type !== CustomerGroupType::Manual) {
            throw ValidationException::withMessages([
                'customer_group' => ['Only manual groups accept explicit membership changes.'],
            ]);
        }

        DB::transaction(function () use ($group, $customer) {
            CustomerGroup::query()->whereKey($group->id)->lockForUpdate()->first();

            $existing = CustomerGroupMember::query()
                ->where('customer_group_id', $group->id)
                ->where('customer_id', $customer->id)
                ->exists();

            if ($existing) {
                return;
            }

            CustomerGroupMember::query()->create([
                'customer_group_id' => $group->id,
                'customer_id' => $customer->id,
                'assigned_at' => now(),
            ]);

            CustomerSegmentMembership::query()->updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'segmentable_type' => SegmentableType::CustomerGroup->value,
                    'segmentable_id' => $group->id,
                ],
                ['entered_at' => now()],
            );

            $this->recordOutboxEvent->handle('CustomerEnteredSegment', 'Customer', $customer->id, [
                'customer_id' => $customer->id,
                'store_id' => $customer->store_id,
                'segmentable_type' => SegmentableType::CustomerGroup->value,
                'segmentable_id' => $group->id,
            ]);
        });
    }
}
