<?php

namespace App\Domain\GraphQL\Support;

use App\Domain\Carts\Models\Cart;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Cart identity travels as the exact same HttpOnly cookie
 * StorefrontCartController already uses (`storefront_cart_token`) —
 * GraphQL and REST carts are interchangeable for a browser session
 * hitting both, per spec section 9 ("switch from REST to GraphQL
 * without business logic changes").
 *
 * A GraphQL resolver has no Response object to attach a cookie to
 * directly (unlike a REST controller action) — this is a per-request
 * singleton (see GraphQLServiceProvider) that a cart resolver records
 * the active Cart into via `remember()`; GraphQLController reads
 * `cookie()` once, after execution, and attaches it to the JsonResponse
 * it returns, the same explicit `->cookie(...)` mechanism REST uses
 * (deliberately not `Cookie::queue()` + `AddQueuedCookiesToResponse`,
 * since that middleware isn't part of the stateless `api` group this
 * route lives in).
 */
final class CartCookie
{
    public const string NAME = 'storefront_cart_token';

    private ?Cart $cart = null;

    public function read(): ?string
    {
        return request()->cookie(self::NAME);
    }

    public function remember(Cart $cart): void
    {
        $this->cart = $cart;
    }

    public function cookie(): ?Cookie
    {
        if ($this->cart === null) {
            return null;
        }

        return Cookie::create(
            name: self::NAME,
            value: $this->cart->token,
            expire: now()->addDays(30)->getTimestamp(),
            path: '/',
            domain: null,
            secure: app()->environment('production'),
            httpOnly: true,
            sameSite: 'lax',
        );
    }
}
