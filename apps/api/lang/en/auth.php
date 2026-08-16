<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    // App-specific keys (customer-facing auth — spec section 3's own
    // `auth.*` namespace, sharing this file with Laravel's framework
    // keys above since both are the same namespace).
    'invalid_credentials' => 'These credentials do not match our records.',
    'account_locked' => 'This account is temporarily locked due to too many failed login attempts. Try again later.',

];
