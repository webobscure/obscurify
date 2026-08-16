<?php

namespace App\Domain\GraphQL\Queries;

use App\Domain\Carts\Application\GetOrCreateCart;
use App\Domain\GraphQL\Support\CartCookie;
use App\Domain\GraphQL\Support\GraphQLContext;
use App\Domain\GraphQL\Support\QueryFieldRegistry;
use App\Domain\GraphQL\Support\TypeRegistry;

/**
 * `cart` — mirrors StorefrontCartController::show exactly, including
 * the eager loads CartTypes' resolvers depend on.
 */
final class CartQueries
{
    private const array CART_EAGER_LOADS = ['items.variant.media', 'items.variant.product.media'];

    public static function register(QueryFieldRegistry $queries, TypeRegistry $types): void
    {
        $queries->register('cart', [
            'type' => $types->get('Cart'),
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $cookie = app(CartCookie::class);
                $cart = app(GetOrCreateCart::class)->handle($cookie->read());
                $cart->load(self::CART_EAGER_LOADS);
                $cookie->remember($cart);

                return $cart;
            },
        ]);
    }
}
