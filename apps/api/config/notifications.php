<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fake notification provider
    |--------------------------------------------------------------------------
    |
    | `enabled` gates whether FakeNotificationProvider is registered with
    | NotificationProviderRegistry at all — same environment/config guard
    | PaymentServiceProvider uses for `payments.fake.enabled`. Unlike the
    | fake payment/shipping providers, there is no HMAC secret here: a
    | notification send is synchronous and has no inbound webhook to
    | verify (see docs/adr/027-notification-center.md).
    |
    */

    'fake' => [
        'enabled' => (bool) env('NOTIFICATIONS_FAKE_ENABLED', in_array(env('APP_ENV'), ['local', 'testing'], true)),
    ],

];
