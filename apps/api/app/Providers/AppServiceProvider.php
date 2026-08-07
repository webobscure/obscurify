<?php

namespace App\Providers;

use App\Domain\Stores\Models\Store;
use App\Domain\Stores\Policies\StorePolicy;
use App\Shared\Tenancy\Contracts\StoreCandidateResolver;
use App\Shared\Tenancy\Resolvers\HeaderStoreCandidateResolver;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);

        $this->app->bind(StoreCandidateResolver::class, HeaderStoreCandidateResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Store::class, StorePolicy::class);

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
