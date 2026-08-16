<?php

namespace App\Domain\GraphQL\Mutations;

use App\Domain\Carts\Application\GetOrCreateCart;
use App\Domain\Checkouts\Application\CompleteCheckout;
use App\Domain\Checkouts\Application\OpenCheckout;
use App\Domain\Checkouts\Application\UpdateCheckout;
use App\Domain\Checkouts\Enums\CheckoutStatus;
use App\Domain\Checkouts\Models\Checkout;
use App\Domain\GraphQL\Exceptions\GraphQLUserError;
use App\Domain\GraphQL\Support\CartCookie;
use App\Domain\GraphQL\Support\GraphQLContext;
use App\Domain\GraphQL\Support\MutationFieldRegistry;
use App\Domain\GraphQL\Support\TypeRegistry;
use App\Domain\GraphQL\Types\CustomerTypes;
use App\Domain\Orders\Models\Order;
use App\Shared\Commerce\Application\IdempotencyKeyStore;
use GraphQL\Type\Definition\Type;
use Illuminate\Validation\ValidationException;

/**
 * `openCheckout`/`updateCheckout`/`completeCheckout` — mirrors
 * StorefrontCheckoutController's store/update/complete exactly,
 * including the mandatory `Idempotency-Key` header on completion (a
 * real HTTP header, not a mutation argument — GraphQL requests are
 * still ordinary HTTP requests, so this is unchanged from REST).
 * Shipping-rate selection and discount-code apply/remove are
 * deliberately out of scope this milestone — see
 * docs/adr/029-graphql-platform.md.
 */
final class CheckoutMutations
{
    private const array CHECKOUT_EAGER_LOADS = ['addresses', 'shippingQuote', 'discountCode'];

    public static function register(MutationFieldRegistry $mutations, TypeRegistry $types): void
    {
        $mutations->register('openCheckout', [
            'type' => $types->get('Checkout'),
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $cart = app(GetOrCreateCart::class)->handle(app(CartCookie::class)->read());

                try {
                    $checkout = app(OpenCheckout::class)->handle($cart);
                } catch (ValidationException $e) {
                    throw new GraphQLUserError(collect($e->errors())->flatten()->implode(' '));
                }

                return self::fresh($checkout);
            },
        ]);

        $mutations->register('updateCheckout', [
            'type' => $types->get('Checkout'),
            'args' => [
                'email' => Type::string(),
                'phone' => Type::string(),
                'shippingAddress' => $types->get('AddressInput'),
                'billingAddress' => $types->get('AddressInput'),
                'billingSameAsShipping' => Type::boolean(),
            ],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $cart = app(GetOrCreateCart::class)->handle(app(CartCookie::class)->read());
                $checkout = self::resolveOpenCheckout($cart);

                $data = array_filter([
                    'email' => $args['email'] ?? null,
                    'phone' => $args['phone'] ?? null,
                    'shipping_address' => isset($args['shippingAddress']) ? CustomerTypes::addressInputToSnakeCase($args['shippingAddress']) : null,
                    'billing_address' => isset($args['billingAddress']) ? CustomerTypes::addressInputToSnakeCase($args['billingAddress']) : null,
                    'billing_same_as_shipping' => $args['billingSameAsShipping'] ?? null,
                ], fn ($v) => $v !== null);

                try {
                    $checkout = app(UpdateCheckout::class)->handle($checkout, $data);
                } catch (ValidationException $e) {
                    throw new GraphQLUserError(collect($e->errors())->flatten()->implode(' '));
                }

                return self::fresh($checkout);
            },
        ]);

        $mutations->register('completeCheckout', [
            'type' => $types->get('Order'),
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $cart = app(GetOrCreateCart::class)->handle(app(CartCookie::class)->read());
                $checkout = self::resolveCheckoutForCompletion($cart);

                $key = request()->header('Idempotency-Key');

                if (! is_string($key) || $key === '') {
                    throw new GraphQLUserError('The Idempotency-Key header is required.');
                }

                $requestHash = hash('sha256', $checkout->id.'|'.request()->getContent());

                // IdempotencyKeyStore persists $callback's return value as
                // {status, body} into a jsonb column (see its own
                // docblock) — an Eloquent model isn't a stable thing to
                // round-trip through that on a replay, so only the id is
                // stored; both the fresh-completion and replay paths
                // re-fetch the real Order the same way afterward.
                $result = app(IdempotencyKeyStore::class)->handle('checkout.complete', $key, $requestHash, function () use ($checkout) {
                    $order = app(CompleteCheckout::class)->handle($checkout);

                    return ['status' => 201, 'body' => ['order_id' => $order->id]];
                });

                return Order::query()->with(['items', 'shippingAddress', 'billingAddress'])->find($result['body']['order_id']);
            },
        ]);
    }

    private static function resolveOpenCheckout(mixed $cart): Checkout
    {
        $checkout = Checkout::query()->where('cart_id', $cart->id)->where('status', CheckoutStatus::Open->value)->latest()->first();

        return $checkout ?? throw GraphQLUserError::notFound('Checkout');
    }

    private static function resolveCheckoutForCompletion(mixed $cart): Checkout
    {
        $checkout = Checkout::query()->where('cart_id', $cart->id)->latest()->first();

        return $checkout ?? throw GraphQLUserError::notFound('Checkout');
    }

    private static function fresh(Checkout $checkout): Checkout
    {
        $checkout->load(self::CHECKOUT_EAGER_LOADS);

        return $checkout;
    }
}
