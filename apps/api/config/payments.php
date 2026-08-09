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
        //
        // Fails CLOSED: the default only enables the fake surface for the
        // two APP_ENV values known to be safe (local, testing). An unset,
        // misspelled, or unrecognized APP_ENV — the common failure mode of
        // copying .env.example and deploying — must never enable a surface
        // that can mark real orders as paid with nothing behind it.
        'enabled' => (bool) env('PAYMENTS_FAKE_ENABLED', in_array(env('APP_ENV'), ['local', 'testing'], true)),
        // No fallback: a secret whose only job is to be unguessable must
        // never ship a guessable default. PaymentServiceProvider refuses
        // to register the fake provider if this is enabled but empty.
        'secret' => (string) env('PAYMENTS_FAKE_SECRET', ''),
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
