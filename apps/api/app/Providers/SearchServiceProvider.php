<?php

namespace App\Providers;

use App\Domain\Search\Support\Providers\DatabaseSearchProvider;
use App\Domain\Search\Support\SearchProviderRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Kept separate from the generic AppServiceProvider — Search is its own
 * module, including how its providers get wired up. Unlike
 * PaymentServiceProvider/NotificationServiceProvider's fake providers
 * (dev/test-only, environment-gated), DatabaseSearchProvider is a real,
 * always-on production feature — spec: "The default implementation
 * must be DatabaseSearchProvider" — so it registers unconditionally,
 * no config flag required.
 */
class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SearchProviderRegistry::class);
    }

    public function boot(): void
    {
        $registry = $this->app->make(SearchProviderRegistry::class);
        $registry->register($this->app->make(DatabaseSearchProvider::class));
    }
}
