<?php

namespace App\Domain\CustomerIntelligence\Support;

use App\Domain\CustomerIntelligence\Models\CustomerMetric;
use App\Domain\CustomerIntelligence\Models\CustomerTagAssignment;
use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Models\Order;
use Illuminate\Support\Collection;

/**
 * Everything SegmentRuleFieldRegistry needs to resolve any field for one
 * customer, gathered once and reused across every rule evaluated against
 * that customer — evaluating N rules for one customer must never re-query
 * tags/address/metric N times.
 */
final class SegmentEvaluationContext
{
    private ?string $countryCode = null;

    private bool $countryCodeResolved = false;

    /** @var Collection<int, string>|null */
    private ?Collection $tagSlugs = null;

    private function __construct(
        public readonly Customer $customer,
        public readonly ?CustomerMetric $metric,
    ) {}

    public static function build(Customer $customer, ?CustomerMetric $metric = null): self
    {
        return new self($customer, $metric ?? CustomerMetric::query()->where('customer_id', $customer->id)->first());
    }

    /**
     * The customer's default shipping address, falling back to their
     * most recent order's shipping address — Customer itself carries no
     * country field of its own.
     */
    public function countryCode(): ?string
    {
        if ($this->countryCodeResolved) {
            return $this->countryCode;
        }

        $this->countryCodeResolved = true;

        $default = $this->customer->addresses()->where('is_default_shipping', true)->first();

        if ($default?->country_code !== null) {
            return $this->countryCode = $default->country_code;
        }

        $lastOrderAddress = Order::query()
            ->where('customer_id', $this->customer->id)
            ->orderByDesc('created_at')
            ->first()
            ?->shippingAddress;

        return $this->countryCode = $lastOrderAddress?->country_code;
    }

    /**
     * @return Collection<int, string>
     */
    public function tagSlugs(): Collection
    {
        if ($this->tagSlugs !== null) {
            return $this->tagSlugs;
        }

        return $this->tagSlugs = CustomerTagAssignment::query()
            ->where('customer_id', $this->customer->id)
            ->with('tag')
            ->get()
            ->pluck('tag.slug')
            ->filter()
            ->values();
    }
}
