<?php

namespace App\Domain\GraphQL\Mutations;

use App\Domain\Carts\Application\AddCartItem;
use App\Domain\Carts\Application\GetOrCreateCart;
use App\Domain\Carts\Application\RemoveCartItem;
use App\Domain\Carts\Application\UpdateCartItem;
use App\Domain\Carts\Models\Cart;
use App\Domain\Carts\Models\CartItem;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\GraphQL\Exceptions\GraphQLUserError;
use App\Domain\GraphQL\Support\CartCookie;
use App\Domain\GraphQL\Support\GraphQLContext;
use App\Domain\GraphQL\Support\MutationFieldRegistry;
use App\Domain\GraphQL\Support\TypeRegistry;
use GraphQL\Type\Definition\Type;
use Illuminate\Validation\ValidationException;

/**
 * `addCartItem`/`updateCartItem`/`removeCartItem` — mirrors
 * StorefrontCartController exactly, including the ownership check
 * (`assertOwnership`) that keeps one guest cart from touching another
 * guest cart's items in the same store.
 */
final class CartMutations
{
    private const array CART_EAGER_LOADS = ['items.variant.media', 'items.variant.product.media'];

    public static function register(MutationFieldRegistry $mutations, TypeRegistry $types): void
    {
        $mutations->register('addCartItem', [
            'type' => $types->get('Cart'),
            'args' => [
                'variantId' => Type::nonNull(Type::id()),
                'quantity' => Type::nonNull(Type::int()),
            ],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $cookie = app(CartCookie::class);
                $cart = app(GetOrCreateCart::class)->handle($cookie->read());
                $variant = ProductVariant::query()->find($args['variantId']);

                if ($variant === null) {
                    throw GraphQLUserError::notFound('Product variant');
                }

                try {
                    app(AddCartItem::class)->handle($cart, $variant, (int) $args['quantity']);
                } catch (ValidationException $e) {
                    throw new GraphQLUserError(collect($e->errors())->flatten()->implode(' '));
                }

                return self::freshCart($cart, $cookie);
            },
        ]);

        $mutations->register('updateCartItem', [
            'type' => $types->get('Cart'),
            'args' => [
                'itemId' => Type::nonNull(Type::id()),
                'quantity' => Type::nonNull(Type::int()),
            ],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $cookie = app(CartCookie::class);
                $cart = app(GetOrCreateCart::class)->handle($cookie->read());
                $item = self::resolveOwnedItem($cart, $args['itemId']);

                try {
                    app(UpdateCartItem::class)->handle($item, (int) $args['quantity']);
                } catch (ValidationException $e) {
                    throw new GraphQLUserError(collect($e->errors())->flatten()->implode(' '));
                }

                return self::freshCart($cart, $cookie);
            },
        ]);

        $mutations->register('removeCartItem', [
            'type' => $types->get('Cart'),
            'args' => ['itemId' => Type::nonNull(Type::id())],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $cookie = app(CartCookie::class);
                $cart = app(GetOrCreateCart::class)->handle($cookie->read());
                $item = self::resolveOwnedItem($cart, $args['itemId']);

                app(RemoveCartItem::class)->handle($item);

                return self::freshCart($cart, $cookie);
            },
        ]);
    }

    private static function resolveOwnedItem(Cart $cart, string $itemId): CartItem
    {
        $item = CartItem::query()->find($itemId);

        if ($item === null || $item->cart_id !== $cart->id) {
            throw GraphQLUserError::notFound('Cart item');
        }

        return $item;
    }

    private static function freshCart(Cart $cart, CartCookie $cookie): Cart
    {
        $cart->load(self::CART_EAGER_LOADS);
        $cookie->remember($cart);

        return $cart;
    }
}
