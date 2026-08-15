<?php

use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\NotificationServiceProvider;
use App\Providers\PaymentServiceProvider;
use App\Providers\ShippingServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    PaymentServiceProvider::class,
    ShippingServiceProvider::class,
    NotificationServiceProvider::class,
];
