<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OAuth token lifetimes
    |--------------------------------------------------------------------------
    |
    | An authorization code is single-use and short-lived on top of that
    | (spec: Authorization Code + PKCE only, no implicit flow). Access
    | tokens are short-lived; refresh tokens are long-lived but rotated
    | (single-use) on every refresh — see AppToken/RefreshAppToken.
    |
    */

    'oauth' => [
        'authorization_code_ttl_minutes' => (int) env('APPS_OAUTH_CODE_TTL_MINUTES', 10),
        'access_token_ttl_minutes' => (int) env('APPS_OAUTH_ACCESS_TOKEN_TTL_MINUTES', 60),
        'refresh_token_ttl_days' => (int) env('APPS_OAUTH_REFRESH_TOKEN_TTL_DAYS', 30),
    ],

];
