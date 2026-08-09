<?php

namespace App\Providers;

use App\Domain\Shipping\Infrastructure\Providers\FakeShippingProvider;
use App\Domain\Shipping\Support\ShippingProviderRegistry;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Kept separate from the generic AppServiceProvider — Shipping is its own
 * module, including how its providers get wired up. Mirrors
 * PaymentServiceProvider exactly.
 */
class ShippingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ShippingProviderRegistry::class);
    }

    public function boot(): void
    {
        // Registration itself is the environment/config guard for the
        // whole fake surface (see config/commerce.php's shipping.fake) —
        // when disabled, "fake" is simply not a provider the registry
        // knows about, the same failure mode as a provider that was never
        // implemented (spec section 40).
        if (! config('commerce.shipping.fake.enabled')) {
            return;
        }

        // Same guard as PaymentServiceProvider, for the same reason: the
        // webhook HMAC secret is the only thing standing between an
        // anonymous request and a forged "delivered" webhook.
        if (config('commerce.shipping.fake.secret') === '') {
            throw new RuntimeException(
                'SHIPPING_FAKE_SECRET must be set when the fake shipping provider is enabled.'
            );
        }

        $registry = $this->app->make(ShippingProviderRegistry::class);
        $registry->register($this->app->make(FakeShippingProvider::class));
    }
}
