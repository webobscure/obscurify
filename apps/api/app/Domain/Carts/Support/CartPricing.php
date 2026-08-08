<?php

namespace App\Domain\Carts\Support;

use App\Domain\Carts\Models\Cart;

/**
 * Cart totals, computed from each line's *current* variant price — Cart
 * doesn't snapshot price the way Order will. See CartResource for why:
 * a cart is a work-in-progress, not a commitment: showing the merchant's
 * latest price is more honest than freezing one at add-to-cart time, and
 * checkout (a later milestone) is where a real snapshot belongs.
 */
final class CartPricing
{
    /**
     * @return array{items_subtotal: int, total: int, currency: string}
     */
    public static function for(Cart $cart): array
    {
        $items = $cart->relationLoaded('items') ? $cart->items : $cart->items()->with('variant')->get();

        $subtotal = (int) $items->sum(fn ($item) => $item->quantity * $item->variant->price_amount);

        return [
            'items_subtotal' => $subtotal,
            'total' => $subtotal,
            'currency' => $cart->currency,
        ];
    }
}
