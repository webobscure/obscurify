<?php

namespace App\Providers;

use App\Domain\Notifications\Support\NotificationProviderRegistry;
use App\Domain\Notifications\Support\Providers\FakeNotificationProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Kept separate from the generic AppServiceProvider — Notifications is
 * its own module, including how its providers get wired up. Mirrors
 * PaymentServiceProvider/ShippingServiceProvider.
 */
class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationProviderRegistry::class);
    }

    public function boot(): void
    {
        if (! config('notifications.fake.enabled')) {
            return;
        }

        $registry = $this->app->make(NotificationProviderRegistry::class);
        $registry->register($this->app->make(FakeNotificationProvider::class));
    }
}
