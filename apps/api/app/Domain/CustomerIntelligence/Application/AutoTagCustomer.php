<?php

namespace App\Domain\CustomerIntelligence\Application;

use App\Domain\CustomerIntelligence\Enums\CustomerTagAssignmentSource;
use App\Domain\CustomerIntelligence\Models\CustomerMetric;
use App\Domain\CustomerIntelligence\Models\CustomerTag;
use App\Domain\CustomerIntelligence\Models\CustomerTagAssignment;
use App\Domain\Customers\Models\Customer;
use App\Shared\Commerce\Application\RecordOutboxEvent;

/**
 * Auto-assigns/removes the four tags spec section 3's example list names
 * that are objectively metric-derived (first-order, repeat-customer,
 * inactive, vip) — every other example tag (Wholesale, Employee, Fraud
 * Risk, ...) stays purely merchant-assigned, since there's no metric
 * that could compute them. "VIP status gained" and "Customer became
 * inactive" (spec section 11) fire as their own named events on top of
 * the generic CustomerTagAssigned, since those two are explicitly called
 * out as automation triggers in their own right.
 */
final class AutoTagCustomer
{
    private const SYSTEM_TAGS = [
        'first-order' => 'First Order',
        'repeat-customer' => 'Repeat Customer',
        'inactive' => 'Inactive',
        'vip' => 'VIP',
    ];

    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    public function handle(Customer $customer, CustomerMetric $metric): void
    {
        $this->sync($customer, 'first-order', $metric->order_count === 1);
        $this->sync($customer, 'repeat-customer', $metric->order_count >= 2);

        $daysSinceLastOrder = $metric->daysSinceLastOrder();
        $isInactive = $metric->order_count > 0
            && $daysSinceLastOrder !== null
            && $daysSinceLastOrder > (int) config('customer_intelligence.inactive_after_days');

        if ($this->sync($customer, 'inactive', $isInactive) === 'gained') {
            $this->recordOutboxEvent->handle('CustomerBecameInactive', 'Customer', $customer->id, [
                'customer_id' => $customer->id,
                'store_id' => $customer->store_id,
                'days_since_last_order' => $daysSinceLastOrder,
            ]);
        }

        $isVip = $metric->lifetime_value_amount >= (int) config('customer_intelligence.vip_lifetime_value_amount');

        if ($this->sync($customer, 'vip', $isVip) === 'gained') {
            $this->recordOutboxEvent->handle('CustomerBecameVip', 'Customer', $customer->id, [
                'customer_id' => $customer->id,
                'store_id' => $customer->store_id,
                'lifetime_value_amount' => $metric->lifetime_value_amount,
            ]);
        }
    }

    /**
     * @return 'gained'|'lost'|'unchanged'
     */
    private function sync(Customer $customer, string $slug, bool $shouldHaveTag): string
    {
        $tag = CustomerTag::query()->where('slug', $slug)->first();

        if ($tag === null) {
            if (! $shouldHaveTag) {
                return 'unchanged';
            }

            $tag = CustomerTag::query()->create([
                'name' => self::SYSTEM_TAGS[$slug],
                'slug' => $slug,
                'is_system' => true,
            ]);
        }

        $existing = CustomerTagAssignment::query()
            ->where('customer_id', $customer->id)
            ->where('customer_tag_id', $tag->id)
            ->first();

        if ($shouldHaveTag && $existing === null) {
            CustomerTagAssignment::query()->create([
                'customer_id' => $customer->id,
                'customer_tag_id' => $tag->id,
                'source' => CustomerTagAssignmentSource::System->value,
                'assigned_at' => now(),
            ]);

            $this->recordOutboxEvent->handle('CustomerTagAssigned', 'Customer', $customer->id, [
                'customer_id' => $customer->id,
                'store_id' => $customer->store_id,
                'tag' => $slug,
            ]);

            return 'gained';
        }

        if (! $shouldHaveTag && $existing !== null) {
            $existing->delete();

            $this->recordOutboxEvent->handle('CustomerTagRemoved', 'Customer', $customer->id, [
                'customer_id' => $customer->id,
                'store_id' => $customer->store_id,
                'tag' => $slug,
            ]);

            return 'lost';
        }

        return 'unchanged';
    }
}
