<?php

namespace App\Domain\Notifications\Support;

use App\Domain\Notifications\Exceptions\UnknownNotificationProviderException;

/**
 * Bound as a singleton (see NotificationServiceProvider) and populated
 * at boot time — mirrors PaymentProviderRegistry/ShippingProviderRegistry
 * exactly. A NotificationProvider DB row's `code` not being registered
 * here (any of NotificationProvider::FUTURE_CODES) fails the same way
 * whether it was never implemented or is just switched off.
 */
final class NotificationProviderRegistry
{
    /** @var array<string, NotificationProviderContract> */
    private array $providers = [];

    public function register(NotificationProviderContract $provider): void
    {
        $this->providers[$provider->code()] = $provider;
    }

    public function has(string $code): bool
    {
        return isset($this->providers[$code]);
    }

    /**
     * @throws UnknownNotificationProviderException
     */
    public function resolve(string $code): NotificationProviderContract
    {
        return $this->providers[$code] ?? throw UnknownNotificationProviderException::forCode($code);
    }
}
