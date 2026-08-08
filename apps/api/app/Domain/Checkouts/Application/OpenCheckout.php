<?php

namespace App\Domain\Checkouts\Application;

use App\Domain\Carts\Models\Cart;
use App\Domain\Carts\Support\CartPricing;
use App\Domain\Checkouts\Enums\CheckoutStatus;
use App\Domain\Checkouts\Models\Checkout;

/**
 * Reuses an existing *open*, non-expired Checkout for this Cart instead
 * of creating a duplicate (spec section 21) — a Cart otherwise
 * accumulates one non-open (expired/completed/cancelled) Checkout row
 * per attempt over time, which is fine, just never more than one *open*
 * one at once.
 */
final class OpenCheckout
{
    public function __construct(private readonly RevalidateCart $revalidateCart) {}

    public function handle(Cart $cart): Checkout
    {
        // Authoritative: an unusable cart (empty/inactive/expired) or an
        // unpurchasable line never gets to open a checkout at all.
        $this->revalidateCart->handle($cart);

        $existing = Checkout::query()
            ->where('cart_id', $cart->id)
            ->where('status', CheckoutStatus::Open->value)
            ->first();

        if ($existing !== null && ! $existing->isExpired()) {
            return $this->refreshTotals($existing, $cart);
        }

        if ($existing !== null) {
            $existing->update(['status' => CheckoutStatus::Expired->value]);
        }

        $totals = CartPricing::for($cart);

        return Checkout::query()->create([
            'cart_id' => $cart->id,
            'currency' => $totals['currency'],
            'items_subtotal_amount' => $totals['items_subtotal'],
            'shipping_amount' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $totals['total'],
            'status' => CheckoutStatus::Open->value,
            'expires_at' => now()->addMinutes((int) config('commerce.checkout.checkout_ttl')),
        ]);
    }

    /**
     * Prices can move between opening the checkout page and revisiting
     * it — keep the snapshot honest rather than showing a stale total.
     * This is *not* the authoritative total either way: CompleteCheckout
     * always recalculates from scratch under lock.
     */
    private function refreshTotals(Checkout $checkout, Cart $cart): Checkout
    {
        $totals = CartPricing::for($cart);

        $checkout->update([
            'items_subtotal_amount' => $totals['items_subtotal'],
            'total_amount' => $totals['items_subtotal'] + $checkout->shipping_amount - $checkout->discount_amount + $checkout->tax_amount,
        ]);

        return $checkout;
    }
}
