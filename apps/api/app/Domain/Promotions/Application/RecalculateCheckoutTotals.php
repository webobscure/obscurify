<?php

namespace App\Domain\Promotions\Application;

use App\Domain\Checkouts\Models\Checkout;
use App\Domain\Promotions\Models\DiscountCode;
use App\Domain\Promotions\Support\PromotionEngine;
use App\Domain\Promotions\Support\PromotionEvaluationResult;

/**
 * The only place Checkout persists a discount total — it never computes
 * one itself (spec section 7), it only asks PromotionEngine and writes
 * down the answer. Called whenever something that could change
 * eligibility changes: opening/reopening a checkout, selecting a shipping
 * rate, and applying/removing a discount code (see
 * StorefrontCheckoutController and its actions). CompleteCheckout does
 * its own final, authoritative evaluation instead of trusting this one,
 * the same way it already re-validates cart/shipping regardless of what's
 * cached on the Checkout row.
 */
final class RecalculateCheckoutTotals
{
    public function __construct(
        private readonly BuildPromotionContext $buildPromotionContext,
        private readonly PromotionEngine $promotionEngine,
    ) {}

    public function handle(Checkout $checkout): PromotionEvaluationResult
    {
        $cart = $checkout->cart()->with('items.variant')->firstOrFail();

        $discountCode = $checkout->discount_code_id !== null
            ? DiscountCode::query()->find($checkout->discount_code_id)
            : null;

        $context = $this->buildPromotionContext->handle($cart, $checkout, $checkout->shipping_amount, $discountCode);
        $result = $this->promotionEngine->handle($context);

        $checkout->update([
            'items_subtotal_amount' => $context->itemsSubtotal,
            'discount_amount' => $result->discountAmount,
            'total_amount' => $context->itemsSubtotal + $checkout->shipping_amount - $result->discountAmount + $checkout->tax_amount,
        ]);

        return $result;
    }
}
