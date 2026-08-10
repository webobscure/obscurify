<?php

namespace App\Domain\Promotions\Support;

use App\Domain\Promotions\Enums\DiscountApplicationTarget;
use App\Domain\Promotions\Enums\PromotionActionType;
use App\Domain\Promotions\Models\DiscountCode;
use App\Domain\Promotions\Models\Promotion;

/**
 * One PromotionAction's computed effect against a specific PromotionContext.
 * `productVariantId` is set only for target=line_item (free_product /
 * line_item_discount / a targeted percentage/fixed action) — it's how
 * CompleteCheckout later maps this back to the OrderItem it discounted,
 * since OrderItem rows don't exist yet at evaluation time.
 */
final readonly class AppliedDiscount
{
    public function __construct(
        public Promotion $promotion,
        public ?DiscountCode $discountCode,
        public PromotionActionType $actionType,
        public DiscountApplicationTarget $target,
        public int $amount,
        public ?string $productVariantId = null,
    ) {}
}
