<?php

namespace App\Domain\Promotions\Support;

use Illuminate\Support\Collection;

/**
 * PromotionEngine's sole output (spec section 7): AppliedDiscounts +
 * FinalTotals. `discountAmount` already folds in every applied discount
 * regardless of target (order, shipping, or a specific line item) — it's
 * the one number Checkout/Order subtract from their total; `applied`
 * carries the breakdown for display and for Order snapshotting.
 */
final readonly class PromotionEvaluationResult
{
    /**
     * @param  Collection<int, AppliedDiscount>  $applied
     */
    public function __construct(
        public Collection $applied,
        public int $discountAmount,
    ) {}
}
