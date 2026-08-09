<?php

namespace App\Domain\Shipping\Support;

use App\Domain\Shipping\Contracts\ShippingProviderContract;
use App\Domain\Shipping\Exceptions\UnknownShippingProviderException;

/**
 * Bound as a singleton (see ShippingServiceProvider) and populated at boot
 * time — mirrors PaymentProviderRegistry exactly.
 */
final class ShippingProviderRegistry
{
    /** @var array<string, ShippingProviderContract> */
    private array $providers = [];

    public function register(ShippingProviderContract $provider): void
    {
        $this->providers[$provider->code()] = $provider;
    }

    public function has(string $code): bool
    {
        return isset($this->providers[$code]);
    }

    /**
     * @throws UnknownShippingProviderException
     */
    public function resolve(string $code): ShippingProviderContract
    {
        return $this->providers[$code] ?? throw UnknownShippingProviderException::forCode($code);
    }

    /**
     * @return list<string>
     */
    public function registeredCodes(): array
    {
        return array_keys($this->providers);
    }
}
