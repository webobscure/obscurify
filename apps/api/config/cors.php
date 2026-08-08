<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The storefront's guest cart uses an HttpOnly cookie, and the Nuxt
    | storefront app is served from a different port than the API — so
    | that request is cross-origin and needs real (non-wildcard) CORS with
    | credentials support. A wildcard origin cannot be combined with
    | credentials per the Fetch spec, so allowed origins must be explicit.
    |
    | Merchant admin (packages/api-client) is unaffected: it authenticates
    | with a bearer token, not cookies, so it never needed credentialed
    | CORS and still works under these tighter settings.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),

    // Local dev only: storefront/admin on any *.localhost subdomain (one
    // per store, see README) on any port — covers the Nuxt dev server's
    // fallback ports and the Playwright e2e port (3100) alike. Production
    // origins go through CORS_ALLOWED_ORIGINS above instead.
    'allowed_origins_patterns' => [
        '#^https?://([a-z0-9-]+\.)?localhost:\d+$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
