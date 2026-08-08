<?php

namespace App\Domain\Checkouts\Application;

use App\Domain\Carts\Application\Concerns\ValidatesCartItemQuantity;
use App\Domain\Carts\Models\Cart;
use App\Domain\Carts\Models\CartItem;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The authoritative gate before reserving inventory or creating an Order:
 * frontend-submitted totals/prices are never trusted (see
 * ARCHITECTURE.md's tenant-isolation-adjacent principle applied to
 * pricing) — every line is re-read from the live Product/Variant here.
 *
 * Reuses AddCartItem/UpdateCartItem's own purchasability rule
 * (ValidatesCartItemQuantity) so "can this be bought right now" is
 * defined in exactly one place for both add-to-cart and checkout.
 */
final class RevalidateCart
{
    use ValidatesCartItemQuantity;

    /**
     * @return Collection<int, array{item: CartItem, variant: ProductVariant, product: Product}>
     */
    public function handle(Cart $cart, bool $lock = false): Collection
    {
        if ($cart->status !== 'active') {
            throw ValidationException::withMessages([
                'cart' => 'This cart is no longer active.',
            ]);
        }

        if ($cart->isExpired()) {
            throw ValidationException::withMessages([
                'cart' => 'This cart has expired.',
            ]);
        }

        $query = CartItem::query()->where('cart_id', $cart->id)->with('variant.product');

        if ($lock) {
            $query->lockForUpdate();
        }

        $items = $query->get();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Cart is empty.',
            ]);
        }

        return $items->map(function (CartItem $item) use ($cart) {
            $variant = $item->variant;

            $this->assertPurchasable($cart, $variant, $item->quantity);

            return [
                'item' => $item,
                'variant' => $variant,
                'product' => $variant->product,
            ];
        });
    }
}
