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

];
