<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    |
    | reservation_ttl: how long an inventory reservation stays active after
    | checkout completion, before inventory:release-expired-reservations
    | frees it back up. Minutes. No payment integration yet, so this is
    | purely "how long we hold stock for a pending_payment order" — not
    | tied to any real payment provider's authorization window.
    |
    | checkout_ttl: how long an *open* (not yet completed) Checkout stays
    | valid before RevalidateCart/CompleteCheckout treats it as expired and
    | a fresh one must be opened from the Cart.
    |
    */

    'checkout' => [
        'reservation_ttl' => (int) env('CHECKOUT_RESERVATION_TTL_MINUTES', 30),
        'checkout_ttl' => (int) env('CHECKOUT_TTL_MINUTES', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shipping
    |--------------------------------------------------------------------------
    |
    | quote_ttl_minutes: how long a selected ShippingQuote stays valid
    | before CompleteCheckout must reject it and the storefront has to
    | re-select (spec section 12's "quote model" policy, chosen over
    | always-recalculate for the same reason CreatePayment locks amount
    | from the Order rather than re-deriving it live).
    |
    | fake: mirrors payments.fake exactly — see that config's own comment
    | for why `enabled` reads env('APP_ENV') directly rather than
    | app()->environment(), and why `secret` has no hardcoded default
    | (config/payments.php's original hardcoded default was the
    | architecture review's TD-2 finding; not repeating that mistake here).
    | rate_markup_percent: the one "deterministic rule" FakeShippingProvider
    | applies on top of each ShippingMethod's own flat price_amount — a
    | configurable value rather than a scattered constant (spec section 8).
    |
    */

    'shipping' => [
        'quote_ttl_minutes' => (int) env('SHIPPING_QUOTE_TTL_MINUTES', 15),

        'webhook' => [
            'replay_tolerance_seconds' => (int) env('SHIPPING_WEBHOOK_REPLAY_TOLERANCE_SECONDS', 300),
        ],

        'fake' => [
            'enabled' => (bool) env('SHIPPING_FAKE_ENABLED', in_array(env('APP_ENV'), ['local', 'testing'], true)),
            'secret' => (string) env('SHIPPING_FAKE_SECRET', ''),
            'rate_markup_percent' => (int) env('SHIPPING_FAKE_RATE_MARKUP_PERCENT', 0),
        ],
    ],

];
