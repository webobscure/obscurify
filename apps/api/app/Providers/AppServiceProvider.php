<?php

namespace App\Providers;

use App\Domain\Apps\Support\CurrentAppContext;
use App\Domain\Customers\Support\CurrentCustomerContext;
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
        $this->app->singleton(CurrentAppContext::class);
        $this->app->singleton(CurrentCustomerContext::class);

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

        // Customer-facing auth endpoints (register/login/password
        // reset/verification resend) — same 5/minute/IP shape as the
        // merchant-admin 'auth' limiter above, kept as its own named
        // limiter since the two guards must never share a bucket.
        RateLimiter::for('customer-auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // A wildcard origin combined with credentialed CORS reflects the
        // request's Origin header back with Access-Control-Allow-Credentials:
        // true, which is strictly worse than a real wildcard — any site can
        // then make authenticated cross-origin requests. CORS_ALLOWED_ORIGINS
        // is unset by default (see .env.example); the natural fix under
        // incident pressure is "just set it to *", so guard against that
        // combination at boot rather than let it ship silently.
        if (in_array('*', config('cors.allowed_origins', []), true) && config('cors.supports_credentials')) {
            throw new \RuntimeException(
                'CORS_ALLOWED_ORIGINS must not be "*" while cors.supports_credentials is true.'
            );
        }
    }
}
