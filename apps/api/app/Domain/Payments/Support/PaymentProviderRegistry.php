<?php

namespace App\Domain\Payments\Support;

use App\Domain\Payments\Contracts\PaymentProviderContract;
use App\Domain\Payments\Exceptions\UnknownPaymentProviderException;

/**
 * Bound as a singleton (see PaymentServiceProvider) and populated at boot
 * time — which providers get registered is itself environment-gated
 * (FakePaymentProvider only when `config('payments.fake.enabled')` is
 * true), so an unknown/disabled provider code fails the same way whether
 * it was never implemented or is just switched off in this environment.
 */
final class PaymentProviderRegistry
{
    /** @var array<string, PaymentProviderContract> */
    private array $providers = [];

    public function register(PaymentProviderContract $provider): void
    {
        $this->providers[$provider->code()] = $provider;
    }

    public function has(string $code): bool
    {
        return isset($this->providers[$code]);
    }

    /**
     * @throws UnknownPaymentProviderException
     */
    public function resolve(string $code): PaymentProviderContract
    {
        return $this->providers[$code] ?? throw UnknownPaymentProviderException::forCode($code);
    }
}
