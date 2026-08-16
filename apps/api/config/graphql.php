<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Introspection
    |--------------------------------------------------------------------------
    |
    | Spec section 10: "Production can disable introspection." Defaults to
    | disabled in production, enabled everywhere else (local/testing/staging)
    | so the playground keeps working during development.
    |
    | Reads the raw env var directly rather than app()->environment(): config
    | files load before the container's `env` binding exists, so calling
    | app()->environment() here crashes the entire boot sequence — see
    | config/payments.php's `fake.enabled` for the same documented
    | constraint. Fails CLOSED: only the two env values known to be safe
    | for introspection (local, testing) default it enabled.
    |
    */

    'disable_introspection' => (bool) env('GRAPHQL_DISABLE_INTROSPECTION', ! in_array(env('APP_ENV'), ['local', 'testing'], true)),

    /*
    |--------------------------------------------------------------------------
    | Playground
    |--------------------------------------------------------------------------
    |
    | Spec section 10: "Create a developer GraphQL explorer." Served at
    | GET /graphql/playground — a static, self-contained HTML page (no
    | external CDN dependency), gated off entirely in production.
    |
    */

    'playground_enabled' => (bool) env('GRAPHQL_PLAYGROUND_ENABLED', in_array(env('APP_ENV'), ['local', 'testing'], true)),

];
