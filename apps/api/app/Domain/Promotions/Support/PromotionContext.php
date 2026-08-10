<?php

namespace App\Domain\Promotions\Support;

use App\Domain\Promotions\Models\DiscountCode;
use Illuminate\Support\Carbon;

/**
 * PromotionEngine's sole input (spec section 7) — everything the rule and
 * action engines need is captured here up front so neither ever queries
 * Cart/Checkout/Customer directly. Built by BuildPromotionContext.
 */
final readonly class PromotionContext
{
    /**
     * @param  array<int, PromotionLine>  $lines
     */
    public function __construct(
        public array $lines,
        public int $itemsSubtotal,
        public int $shippingAmount,
        public string $currency,
        public ?string $countryCode,
        public ?string $customerId,
        public ?string $customerEmail,
        public ?DiscountCode $appliedDiscountCode,
        public Carbon $now,
    ) {}

    public function totalQuantity(): int
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $total += $line->quantity;
        }

        return $total;
    }

    public function orderTotal(): int
    {
        return $this->itemsSubtotal + $this->shippingAmount;
    }
}
