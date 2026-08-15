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

final class RemoveCustomerFromGroup
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
            $deleted = CustomerGroupMember::query()
                ->where('customer_group_id', $group->id)
                ->where('customer_id', $customer->id)
                ->delete();

            if ($deleted === 0) {
                return;
            }

            CustomerSegmentMembership::query()
                ->where('customer_id', $customer->id)
                ->where('segmentable_type', SegmentableType::CustomerGroup->value)
                ->where('segmentable_id', $group->id)
                ->delete();

            $this->recordOutboxEvent->handle('CustomerLeftSegment', 'Customer', $customer->id, [
                'customer_id' => $customer->id,
                'store_id' => $customer->store_id,
                'segmentable_type' => SegmentableType::CustomerGroup->value,
                'segmentable_id' => $group->id,
            ]);
        });
    }
}
