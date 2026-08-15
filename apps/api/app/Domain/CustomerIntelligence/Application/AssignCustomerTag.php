<?php

namespace App\Domain\CustomerIntelligence\Application;

use App\Domain\CustomerIntelligence\Enums\CustomerTagAssignmentSource;
use App\Domain\CustomerIntelligence\Exceptions\SystemCustomerTagException;
use App\Domain\CustomerIntelligence\Models\CustomerTag;
use App\Domain\CustomerIntelligence\Models\CustomerTagAssignment;
use App\Domain\Customers\Models\Customer;
use App\Shared\Commerce\Application\RecordOutboxEvent;

final class AssignCustomerTag
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    public function handle(Customer $customer, CustomerTag $tag): CustomerTagAssignment
    {
        if ($tag->is_system) {
            throw SystemCustomerTagException::cannotAssignManually();
        }

        $existing = CustomerTagAssignment::query()
            ->where('customer_id', $customer->id)
            ->where('customer_tag_id', $tag->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $assignment = CustomerTagAssignment::query()->create([
            'customer_id' => $customer->id,
            'customer_tag_id' => $tag->id,
            'source' => CustomerTagAssignmentSource::Manual->value,
            'assigned_at' => now(),
        ]);

        $this->recordOutboxEvent->handle('CustomerTagAssigned', 'Customer', $customer->id, [
            'customer_id' => $customer->id,
            'store_id' => $customer->store_id,
            'tag' => $tag->slug,
        ]);

        return $assignment;
    }
}
