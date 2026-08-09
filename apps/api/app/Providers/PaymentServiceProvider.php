<?php

namespace App\Providers;

use App\Domain\Payments\Infrastructure\Providers\FakePaymentProvider;
use App\Domain\Payments\Support\PaymentProviderRegistry;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Kept separate from the generic AppServiceProvider — Payments is its own
 * module (see spec section 1), including how its providers get wired up.
 */
class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentProviderRegistry::class);
    }

    public function boot(): void
    {
        // Registration itself is the environment/config guard for the
        // whole fake surface (see config/payments.php) — when disabled,
        // "fake" is simply not a provider the registry knows about, the
        // same failure mode as a provider that was never implemented.
        if (! config('payments.fake.enabled')) {
            return;
        }

        // The webhook HMAC secret is the only thing standing between an
        // anonymous request and a forged "payment succeeded" webhook — an
        // empty secret must never be able to silently pass verification.
        // Fail loudly at boot rather than let every signature check pass.
        if (config('payments.fake.secret') === '') {
            throw new RuntimeException(
                'PAYMENTS_FAKE_SECRET must be set when the fake payment provider is enabled.'
            );
        }

        $registry = $this->app->make(PaymentProviderRegistry::class);
        $registry->register($this->app->make(FakePaymentProvider::class));
    }
}
