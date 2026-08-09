<?php

namespace App\Domain\Storefront\Http\Controllers;

use App\Domain\Carts\Application\GetOrCreateCart;
use App\Domain\Carts\Models\Cart;
use App\Domain\Checkouts\Application\CompleteCheckout;
use App\Domain\Checkouts\Application\OpenCheckout;
use App\Domain\Checkouts\Application\UpdateCheckout;
use App\Domain\Checkouts\Enums\CheckoutStatus;
use App\Domain\Checkouts\Models\Checkout;
use App\Domain\Checkouts\Models\CheckoutAddress;
use App\Domain\Shipping\Application\CalculateShippingRates;
use App\Domain\Shipping\Application\SelectShippingRate;
use App\Domain\Shipping\Support\ShippingRateContext;
use App\Domain\Storefront\Http\Requests\CompleteCheckoutRequest;
use App\Domain\Storefront\Http\Requests\SelectCheckoutShippingRequest;
use App\Domain\Storefront\Http\Requests\UpdateCheckoutRequest;
use App\Domain\Storefront\Http\Resources\CheckoutResource;
use App\Domain\Storefront\Http\Resources\OrderConfirmationResource;
use App\Domain\Storefront\Http\Resources\StorefrontShippingRateResource;
use App\Http\Controllers\Controller;
use App\Shared\Commerce\Application\IdempotencyKeyStore;
use App\Shared\Commerce\Enums\AddressType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class StorefrontCheckoutController extends Controller
{
    private const COOKIE_NAME = 'storefront_cart_token';

    private const array CHECKOUT_EAGER_LOADS = ['addresses', 'shippingQuote'];

    public function store(Request $request, GetOrCreateCart $getOrCreateCart, OpenCheckout $action): JsonResponse
    {
        $cart = $getOrCreateCart->handle($request->cookie(self::COOKIE_NAME));

        $checkout = $action->handle($cart);

        return $this->checkoutResponse($checkout);
    }

    public function update(UpdateCheckoutRequest $request, GetOrCreateCart $getOrCreateCart, UpdateCheckout $action): JsonResponse
    {
        $cart = $getOrCreateCart->handle($request->cookie(self::COOKIE_NAME));
        $checkout = $this->resolveOpenCheckout($cart);

        $checkout = $action->handle($checkout, $request->validated());

        return $this->checkoutResponse($checkout);
    }

    /**
     * Live-calculated on every call, never cached (spec section 9: "do not
     * calculate from frontend") — reads the checkout's already-saved
     * shipping address, so PATCH .../checkout with a shipping_address must
     * happen first.
     */
    public function shippingRates(Request $request, GetOrCreateCart $getOrCreateCart, CalculateShippingRates $action): AnonymousResourceCollection
    {
        $cart = $getOrCreateCart->handle($request->cookie(self::COOKIE_NAME));
        $checkout = $this->resolveOpenCheckout($cart);

        $context = $this->shippingRateContext($checkout);

        return StorefrontShippingRateResource::collection($action->handle($context));
    }

    public function selectShipping(SelectCheckoutShippingRequest $request, GetOrCreateCart $getOrCreateCart, SelectShippingRate $action): JsonResponse
    {
        $cart = $getOrCreateCart->handle($request->cookie(self::COOKIE_NAME));
        $checkout = $this->resolveOpenCheckout($cart);

        $data = $request->validated();

        $checkout = $action->handle(
            $checkout,
            $data['provider'],
            $data['service_code'] ?? null,
            $data['shipping_method_id'] ?? null,
        );

        return $this->checkoutResponse($checkout);
    }

    /**
     * Requires an Idempotency-Key header (stricter than the spec's letter,
     * deliberately — the storefront frontend always controls sending it,
     * and duplicate-order prevention on checkout completion is critical
     * enough not to make it optional). The key is scoped per-store by
     * IdempotencyKey's own tenant column, and per-checkout by folding the
     * checkout id into the request hash: reusing the same key against a
     * different checkout is treated as a conflicting payload, not a
     * replay, since replaying a stale order here would be a real bug.
     */
    public function complete(CompleteCheckoutRequest $request, GetOrCreateCart $getOrCreateCart, CompleteCheckout $action, IdempotencyKeyStore $idempotencyKeyStore): JsonResponse
    {
        $cart = $getOrCreateCart->handle($request->cookie(self::COOKIE_NAME));
        $checkout = $this->resolveCheckoutForCompletion($cart);

        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || $key === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => 'The Idempotency-Key header is required.',
            ]);
        }

        $requestHash = hash('sha256', $checkout->id.'|'.$request->getContent());

        $result = $idempotencyKeyStore->handle('checkout.complete', $key, $requestHash, function () use ($checkout, $action) {
            $order = $action->handle($checkout)->load(['items', 'shippingAddress', 'billingAddress']);

            return [
                'status' => 201,
                'body' => (new OrderConfirmationResource($order))->resolve(),
            ];
        });

        return response()->json(['data' => $result['body']], $result['status']);
    }

    /**
     * A checkout belongs to this visitor only if it was opened against
     * *their* cart (resolved solely from the secure cart cookie) — never
     * trusted from a client-supplied checkout id. Tenant isolation is a
     * second, independent guarantee from Checkout's BelongsToTenant scope.
     */
    private function resolveOpenCheckout(Cart $cart): Checkout
    {
        $checkout = Checkout::query()
            ->where('cart_id', $cart->id)
            ->where('status', CheckoutStatus::Open->value)
            ->latest()
            ->first();

        if ($checkout === null) {
            throw new NotFoundHttpException;
        }

        return $checkout;
    }

    /**
     * Deliberately not filtered to status=open: a retried completion
     * request (network drop, client resend) must still be able to resolve
     * *the same* checkout row after the first attempt already completed
     * it, so the request hash can be rebuilt identically and the
     * idempotency store can replay the original order instead of 404ing.
     * CompleteCheckout itself is what enforces the open/not-expired
     * invariant for a genuinely first attempt.
     */
    private function resolveCheckoutForCompletion(Cart $cart): Checkout
    {
        $checkout = Checkout::query()
            ->where('cart_id', $cart->id)
            ->latest()
            ->first();

        if ($checkout === null) {
            throw new NotFoundHttpException;
        }

        return $checkout;
    }

    private function checkoutResponse(Checkout $checkout): JsonResponse
    {
        $checkout->load(self::CHECKOUT_EAGER_LOADS);

        return (new CheckoutResource($checkout))->response()->setStatusCode(200);
    }

    private function shippingRateContext(Checkout $checkout): ShippingRateContext
    {
        $shippingAddress = CheckoutAddress::query()
            ->where('checkout_id', $checkout->id)
            ->where('type', AddressType::Shipping->value)
            ->first();

        if ($shippingAddress === null) {
            throw ValidationException::withMessages([
                'shipping_address' => 'A shipping address is required before requesting shipping rates.',
            ]);
        }

        return new ShippingRateContext(
            countryCode: $shippingAddress->country_code ?? '',
            region: $shippingAddress->region,
            postalCode: $shippingAddress->postal_code,
            currency: $checkout->currency,
        );
    }
}
