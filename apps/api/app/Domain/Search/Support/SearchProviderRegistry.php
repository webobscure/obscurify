<?php

namespace App\Domain\Search\Support;

use App\Domain\Search\Exceptions\UnknownSearchProviderException;

/**
 * Bound as a singleton (see SearchServiceProvider) and populated at
 * boot time — mirrors PaymentProviderRegistry/NotificationProviderRegistry
 * exactly.
 */
final class SearchProviderRegistry
{
    /** @var array<string, SearchProviderContract> */
    private array $providers = [];

    public function register(SearchProviderContract $provider): void
    {
        $this->providers[$provider->code()] = $provider;
    }

    public function has(string $code): bool
    {
        return isset($this->providers[$code]);
    }

    /**
     * @throws UnknownSearchProviderException
     */
    public function resolve(string $code): SearchProviderContract
    {
        return $this->providers[$code] ?? throw UnknownSearchProviderException::forCode($code);
    }
}
