<?php

namespace App\Domain\RussianCommerce\Support;

use App\Domain\RussianCommerce\Exceptions\UnknownFiscalizationProviderException;

/**
 * Bound as a singleton (see RussianCommerceServiceProvider) and
 * populated at boot time — mirrors PaymentProviderRegistry/
 * SearchProviderRegistry exactly.
 */
final class FiscalizationProviderRegistry
{
    /** @var array<string, FiscalizationProviderContract> */
    private array $providers = [];

    public function register(FiscalizationProviderContract $provider): void
    {
        $this->providers[$provider->code()] = $provider;
    }

    public function has(string $code): bool
    {
        return isset($this->providers[$code]);
    }

    /**
     * @throws UnknownFiscalizationProviderException
     */
    public function resolve(string $code): FiscalizationProviderContract
    {
        return $this->providers[$code] ?? throw UnknownFiscalizationProviderException::forCode($code);
    }
}
