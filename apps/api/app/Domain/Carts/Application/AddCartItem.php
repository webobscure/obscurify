<?php

namespace App\Domain\Carts\Application;

use App\Domain\Carts\Application\Concerns\ValidatesCartItemQuantity;
use App\Domain\Carts\Models\Cart;
use App\Domain\Carts\Models\CartItem;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

final class AddCartItem
{
    use ValidatesCartItemQuantity;

    /**
     * Locks the existing line (if any) for the duration of the
     * transaction so two concurrent "add to cart" calls for the same
     * variant can't race and silently drop one of the increments.
     */
    public function handle(Cart $cart, ProductVariant $variant, int $quantity): CartItem
    {
        return DB::transaction(function () use ($cart, $variant, $quantity) {
            $existing = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_variant_id', $variant->id)
                ->lockForUpdate()
                ->first();

            $newQuantity = ($existing === null ? 0 : $existing->quantity) + $quantity;

            $this->assertPurchasable($cart, $variant, $newQuantity);

            if ($existing !== null) {
                $existing->update(['quantity' => $newQuantity]);

                return $existing;
            }

            return CartItem::query()->create([
                'cart_id' => $cart->id,
                'product_variant_id' => $variant->id,
                'quantity' => $newQuantity,
            ]);
        });
    }
}
