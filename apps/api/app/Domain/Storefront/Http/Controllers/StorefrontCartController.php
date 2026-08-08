<?php

namespace App\Domain\Storefront\Http\Controllers;

use App\Domain\Carts\Application\AddCartItem;
use App\Domain\Carts\Application\GetOrCreateCart;
use App\Domain\Carts\Application\RemoveCartItem;
use App\Domain\Carts\Application\UpdateCartItem;
use App\Domain\Carts\Models\Cart;
use App\Domain\Carts\Models\CartItem;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Storefront\Http\Requests\AddCartItemRequest;
use App\Domain\Storefront\Http\Requests\UpdateCartItemRequest;
use App\Domain\Storefront\Http\Resources\CartResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class StorefrontCartController extends Controller
{
    private const COOKIE_NAME = 'storefront_cart_token';

    private const array CART_EAGER_LOADS = ['items.variant.media', 'items.variant.product.media'];

    public function show(Request $request, GetOrCreateCart $getOrCreateCart): JsonResponse
    {
        $cart = $getOrCreateCart->handle($request->cookie(self::COOKIE_NAME));

        return $this->cartResponse($cart);
    }

    public function addItem(AddCartItemRequest $request, GetOrCreateCart $getOrCreateCart, AddCartItem $action): JsonResponse
    {
        $cart = $getOrCreateCart->handle($request->cookie(self::COOKIE_NAME));
        $variant = ProductVariant::query()->findOrFail($request->validated('variant_id'));

        $action->handle($cart, $variant, (int) $request->validated('quantity'));

        return $this->cartResponse($cart);
    }

    public function updateItem(UpdateCartItemRequest $request, CartItem $item, GetOrCreateCart $getOrCreateCart, UpdateCartItem $action): JsonResponse
    {
        $cart = $getOrCreateCart->handle($request->cookie(self::COOKIE_NAME));
        $this->assertOwnership($cart, $item);

        $action->handle($item, (int) $request->validated('quantity'));

        return $this->cartResponse($cart);
    }

    public function removeItem(Request $request, CartItem $item, GetOrCreateCart $getOrCreateCart, RemoveCartItem $action): Response
    {
        $cart = $getOrCreateCart->handle($request->cookie(self::COOKIE_NAME));
        $this->assertOwnership($cart, $item);

        $action->handle($item);

        return response()->noContent()->cookie($this->cookie($cart));
    }

    /**
     * A CartItem's own tenant scope only guarantees it belongs to the
     * active store — a second guest cart in the *same* store must not be
     * able to touch it. Ownership is decided solely by the resolved
     * cart's token, never by the (unguessable but not secret) item id.
     */
    private function assertOwnership(Cart $cart, CartItem $item): void
    {
        if ($item->cart_id !== $cart->id) {
            throw new NotFoundHttpException;
        }
    }

    private function cartResponse(Cart $cart): JsonResponse
    {
        $cart->load(self::CART_EAGER_LOADS);

        return (new CartResource($cart))
            ->response()
            ->setStatusCode(200)
            ->cookie($this->cookie($cart));
    }

    private function cookie(Cart $cart): Cookie
    {
        return cookie(
            name: self::COOKIE_NAME,
            value: $cart->token,
            minutes: 60 * 24 * 30,
            path: '/',
            domain: null,
            secure: app()->environment('production'),
            httpOnly: true,
            sameSite: 'lax',
        );
    }
}
