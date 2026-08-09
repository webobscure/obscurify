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
    | rate_markup_percent: the one flat markup FakeShippingProvider applies
    | on top of every computed price — a configurable value rather than a
    | scattered constant (spec section 8).
    |
    | services: the deterministic rate table (spec section 2/8) —
    | base_price_amount + price_per_kg_amount * billable weight (rounded up
    | to the next whole kg), per service_code. A ShippingMethod whose
    | service_code isn't listed here falls back to its own flat
    | price_amount (see FakeShippingProvider::calculateRates) — this table
    | only makes *known* fake services weight-aware, it doesn't replace
    | ShippingMethod as the source of truth for which services a zone
    | offers.
    |
    | domestic_country_code / international_surcharge_percent: the one
    | destination-dependent rule (spec section 2: "should depend on
    | destination country") — deliberately simple (domestic vs.
    | international), not a per-country rate table, matching "do not
    | create a huge shipping calculator."
    |
    | volumetric_divisor: cm³-per-kg used to derive dimensional
    | ("volumetric") weight from a variant's length/width/height — 5000 is
    | the common carrier-industry default. See ShipmentWeightCalculator.
    |
    | pickup_points: the fake carrier's static, deterministic pickup-point
    | network (spec section 5) — no real maps, no geocoding.
    |
    | delayed_lifecycle: delay (seconds) before each queued fake lifecycle
    | event fires when the "simulate full delivery" dev action is used
    | (spec section 14) — SimulateFakeShippingLifecycleJob. Degrades to
    | effectively-immediate under the sync queue driver (tests), genuinely
    | delayed under a real queue worker.
    |
    | failure_simulation: gates the dev-only rate/shipment-creation failure
    | triggers (spec section 15) — allowlisted to local/testing the same
    | way `enabled` above is, never a plain boolean env default.
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

            'services' => [
                'standard' => [
                    'name' => 'Standard Shipping',
                    'base_price_amount' => 30000,
                    'price_per_kg_amount' => 5000,
                    'estimated_days_min' => 3,
                    'estimated_days_max' => 5,
                ],
                'express' => [
                    'name' => 'Express Shipping',
                    'base_price_amount' => 80000,
                    'price_per_kg_amount' => 12000,
                    'estimated_days_min' => 1,
                    'estimated_days_max' => 2,
                ],
                'pickup' => [
                    'name' => 'Pickup Point',
                    'base_price_amount' => 15000,
                    'price_per_kg_amount' => 3000,
                    'estimated_days_min' => 2,
                    'estimated_days_max' => 4,
                ],
            ],

            'domestic_country_code' => (string) env('SHIPPING_FAKE_DOMESTIC_COUNTRY', 'RU'),
            'international_surcharge_percent' => (int) env('SHIPPING_FAKE_INTL_SURCHARGE_PERCENT', 50),
            'volumetric_divisor' => (int) env('SHIPPING_FAKE_VOLUMETRIC_DIVISOR', 5000),

            'pickup_points' => [
                [
                    'id' => 'fake-pickup-moscow-1',
                    'name' => 'Fake Pickup — Tverskaya',
                    'address' => 'Tverskaya St, 7',
                    'city' => 'Moscow',
                    'country_code' => 'RU',
                    'postal_code' => '125009',
                    'opening_hours' => 'Mon-Sat 09:00-21:00',
                    'latitude' => 55.7616,
                    'longitude' => 37.6079,
                ],
                [
                    'id' => 'fake-pickup-moscow-2',
                    'name' => 'Fake Pickup — Arbat',
                    'address' => 'Arbat St, 22',
                    'city' => 'Moscow',
                    'country_code' => 'RU',
                    'postal_code' => '119002',
                    'opening_hours' => 'Mon-Sun 10:00-20:00',
                    'latitude' => 55.7495,
                    'longitude' => 37.5931,
                ],
                [
                    'id' => 'fake-pickup-spb-1',
                    'name' => 'Fake Pickup — Nevsky',
                    'address' => 'Nevsky Ave, 28',
                    'city' => 'Saint Petersburg',
                    'country_code' => 'RU',
                    'postal_code' => '191186',
                    'opening_hours' => 'Mon-Sat 10:00-19:00',
                    'latitude' => 59.9343,
                    'longitude' => 30.3351,
                ],
            ],

            'delayed_lifecycle' => [
                'accepted_delay_seconds' => (int) env('SHIPPING_FAKE_DELAY_ACCEPTED_SECONDS', 10),
                'in_transit_delay_seconds' => (int) env('SHIPPING_FAKE_DELAY_IN_TRANSIT_SECONDS', 30),
                'out_for_delivery_delay_seconds' => (int) env('SHIPPING_FAKE_DELAY_OUT_FOR_DELIVERY_SECONDS', 60),
                'delivered_delay_seconds' => (int) env('SHIPPING_FAKE_DELAY_DELIVERED_SECONDS', 90),
            ],

            'failure_simulation' => [
                'enabled' => (bool) env('SHIPPING_FAKE_FAILURE_SIMULATION_ENABLED', in_array(env('APP_ENV'), ['local', 'testing'], true)),
            ],
        ],
    ],

];
