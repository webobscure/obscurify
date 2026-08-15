<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Customer token lifetimes
    |--------------------------------------------------------------------------
    |
    | Same shape as config/apps.php's oauth block — access tokens are
    | short-lived, refresh tokens are long-lived but rotated (single-use)
    | on every refresh. See CustomerAccessToken/IssueCustomerTokenPair.
    |
    */

    'access_token_ttl_minutes' => (int) env('CUSTOMERS_ACCESS_TOKEN_TTL_MINUTES', 15),
    'refresh_token_ttl_days' => (int) env('CUSTOMERS_REFRESH_TOKEN_TTL_DAYS', 30),
    'session_ttl_days' => (int) env('CUSTOMERS_SESSION_TTL_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Password reset / email verification tokens
    |--------------------------------------------------------------------------
    */

    'password_reset_ttl_minutes' => (int) env('CUSTOMERS_PASSWORD_RESET_TTL_MINUTES', 60),
    'email_verification_ttl_hours' => (int) env('CUSTOMERS_EMAIL_VERIFICATION_TTL_HOURS', 48),

    /*
    |--------------------------------------------------------------------------
    | Account lock protection
    |--------------------------------------------------------------------------
    |
    | After this many consecutive failed password attempts against one
    | CustomerIdentity, it is locked for `lockout_minutes` — see
    | AuthenticateCustomer.
    |
    */

    'max_failed_attempts' => (int) env('CUSTOMERS_MAX_FAILED_ATTEMPTS', 5),
    'lockout_minutes' => (int) env('CUSTOMERS_LOCKOUT_MINUTES', 15),

];
