<?php

namespace App\Domain\CustomerIntelligence\Application;

use App\Domain\CustomerIntelligence\Exceptions\SystemCustomerTagException;
use App\Domain\CustomerIntelligence\Models\CustomerTag;
use App\Domain\CustomerIntelligence\Models\CustomerTagAssignment;
use App\Domain\Customers\Models\Customer;
use App\Shared\Commerce\Application\RecordOutboxEvent;

final class RemoveCustomerTag
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    public function handle(Customer $customer, CustomerTag $tag): void
    {
        if ($tag->is_system) {
            throw SystemCustomerTagException::cannotAssignManually();
        }

        $deleted = CustomerTagAssignment::query()
            ->where('customer_id', $customer->id)
            ->where('customer_tag_id', $tag->id)
            ->delete();

        if ($deleted === 0) {
            return;
        }

        $this->recordOutboxEvent->handle('CustomerTagRemoved', 'Customer', $customer->id, [
            'customer_id' => $customer->id,
            'store_id' => $customer->store_id,
            'tag' => $tag->slug,
        ]);
    }
}
