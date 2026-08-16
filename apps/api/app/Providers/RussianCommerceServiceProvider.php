<?php

namespace App\Providers;

use App\Domain\RussianCommerce\Support\FiscalizationProviderRegistry;
use App\Domain\RussianCommerce\Support\Providers\FakeFiscalizationProvider;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Kept separate from the generic AppServiceProvider — Russian Commerce is
 * its own module (spec section 1), including how its fiscalization
 * providers get wired up. Mirrors PaymentServiceProvider exactly.
 */
class RussianCommerceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FiscalizationProviderRegistry::class);
    }

    public function boot(): void
    {
        // Registration itself is the environment/config guard for the
        // whole fake surface (see config/russian_commerce.php) — when
        // disabled, "fake" is simply not a provider the registry knows
        // about, the same failure mode as a provider that was never
        // implemented.
        if (! config('russian_commerce.fake_fiscalization.enabled')) {
            return;
        }

        // The callback HMAC secret is the only thing standing between an
        // anonymous request and a forged "fiscalized" callback — an
        // empty secret must never be able to silently pass verification.
        // Fail loudly at boot rather than let every signature check pass.
        if (config('russian_commerce.fake_fiscalization.secret') === '') {
            throw new RuntimeException(
                'RUSSIAN_COMMERCE_FAKE_FISCALIZATION_SECRET must be set when the fake fiscalization provider is enabled.'
            );
        }

        $registry = $this->app->make(FiscalizationProviderRegistry::class);
        $registry->register($this->app->make(FakeFiscalizationProvider::class));
    }
}
