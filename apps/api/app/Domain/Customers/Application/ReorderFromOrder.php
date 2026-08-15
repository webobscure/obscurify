<?php

namespace App\Domain\Customers\Application;

use App\Domain\Carts\Application\AddCartItem;
use App\Domain\Carts\Application\GetOrCreateCart;
use App\Domain\Carts\Models\Cart;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Orders\Models\Order;
use Illuminate\Validation\ValidationException;

/**
 * "Buy again" — never copies a price. It only ever resolves the *live*
 * ProductVariant for each OrderItem's product_variant_id and hands it to
 * the same AddCartItem the regular storefront "add to cart" flow uses;
 * pricing is then computed fresh downstream at checkout time
 * (CompleteCheckout reads ProductVariant::price_amount at completion,
 * never anything cached on the old order — see
 * docs/architecture/customer-accounts.md). A variant that's since been
 * deleted, deactivated, or gone out of stock is skipped rather than
 * failing the whole reorder.
 */
final class ReorderFromOrder
{
    public function __construct(
        private readonly GetOrCreateCart $getOrCreateCart,
        private readonly AddCartItem $addCartItem,
    ) {}

    /**
     * @return array{cart: Cart, skipped: list<array{order_item_id: string, product_title: string, reason: string}>}
     */
    public function handle(Order $order, ?string $cartToken): array
    {
        $cart = $this->getOrCreateCart->handle($cartToken);

        $skipped = [];

        foreach ($order->items as $item) {
            if ($item->product_variant_id === null) {
                $skipped[] = [
                    'order_item_id' => $item->id,
                    'product_title' => $item->product_title,
                    'reason' => 'no_longer_available',
                ];

                continue;
            }

            $variant = ProductVariant::query()->find($item->product_variant_id);

            if ($variant === null) {
                $skipped[] = [
                    'order_item_id' => $item->id,
                    'product_title' => $item->product_title,
                    'reason' => 'no_longer_available',
                ];

                continue;
            }

            try {
                $this->addCartItem->handle($cart, $variant, $item->quantity);
            } catch (ValidationException) {
                $skipped[] = [
                    'order_item_id' => $item->id,
                    'product_title' => $item->product_title,
                    'reason' => 'unavailable',
                ];
            }
        }

        return ['cart' => $cart, 'skipped' => $skipped];
    }
}
