<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fake fiscalization provider
    |--------------------------------------------------------------------------
    |
    | `enabled` gates the entire fake surface, exactly like
    | payments.fake.enabled (see that file's own docblock for why this
    | reads the raw env var directly rather than app()->environment()).
    | `secret` signs/verifies the fake provider's simulated callbacks.
    |
    */

    'fake_fiscalization' => [
        'enabled' => (bool) env('RUSSIAN_COMMERCE_FAKE_FISCALIZATION_ENABLED', in_array(env('APP_ENV'), ['local', 'testing'], true)),
        'secret' => (string) env('RUSSIAN_COMMERCE_FAKE_FISCALIZATION_SECRET', ''),
    ],

];
