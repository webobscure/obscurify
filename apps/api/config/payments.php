<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fake payment provider
    |--------------------------------------------------------------------------
    |
    | `enabled` gates the ENTIRE fake surface: whether FakePaymentProvider
    | is registered with PaymentProviderRegistry at all, whether the
    | dev-only fake payment routes exist, and whether the storefront is
    | told a fake option is available. This is an environment/config
    | guard, not just UI hiding — see spec section 11. Defaults to off in
    | production and on everywhere else.
    |
    | `secret` signs/verifies the fake provider's simulated webhooks
    | (HMAC-SHA256) — never logged, never exposed via any API response.
    |
    */

    'fake' => [
        // Reads the raw env var directly rather than app()->environment():
        // config files load before the container's `env` binding exists,
        // so calling app()->environment() here crashes the entire boot
        // sequence (a real bug, caught by every command failing silently).
        'enabled' => (bool) env('PAYMENTS_FAKE_ENABLED', env('APP_ENV') !== 'production'),
        'secret' => (string) env('PAYMENTS_FAKE_SECRET', 'fake-payment-provider-secret'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook replay tolerance
    |--------------------------------------------------------------------------
    |
    | A signed webhook whose `timestamp` is older than this many seconds
    | is rejected outright, before signature/idempotency checks — bounds
    | how long a captured/replayed request stays valid even if the
    | signature and event id would otherwise check out.
    |
    */

    'webhook' => [
        'replay_tolerance_seconds' => (int) env('PAYMENTS_WEBHOOK_REPLAY_TOLERANCE_SECONDS', 300),
    ],

];
