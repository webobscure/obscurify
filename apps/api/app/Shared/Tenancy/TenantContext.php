<?php

namespace App\Shared\Tenancy;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Exceptions\TenantContextMissingException;

/**
 * Holds the active Store for the current request/job lifecycle.
 *
 * Bound as a singleton in the container. Under PHP-FPM the container (and
 * therefore this instance) is discarded at the end of every request, but
 * middleware still clears it explicitly for defense in depth and to keep
 * the class safe under long-running workers (Octane, queue workers).
 */
final class TenantContext
{
    private ?Store $store = null;

    public function set(Store $store): void
    {
        $this->store = $store;
    }

    public function clear(): void
    {
        $this->store = null;
    }

    public function has(): bool
    {
        return $this->store !== null;
    }

    /**
     * @throws TenantContextMissingException when no tenant is active.
     */
    public function store(): Store
    {
        if ($this->store === null) {
            throw TenantContextMissingException::noActiveStore();
        }

        return $this->store;
    }

    /**
     * @throws TenantContextMissingException when no tenant is active.
     */
    public function storeId(): string
    {
        return $this->store()->id;
    }

    /**
     * Run a callback with the given store active as the tenant, restoring
     * whatever tenant was previously active afterwards. Intended for queue
     * jobs and console commands that must explicitly restore tenant
     * context outside of the HTTP request lifecycle.
     */
    public function scope(Store $store, callable $callback): mixed
    {
        $previous = $this->store;
        $this->store = $store;

        try {
            return $callback();
        } finally {
            $this->store = $previous;
        }
    }
}
