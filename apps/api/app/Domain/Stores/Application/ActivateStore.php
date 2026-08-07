<?php

namespace App\Domain\Stores\Application;

use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Tenancy\Exceptions\TenantAccessDeniedException;
use Illuminate\Support\Facades\Gate;

/**
 * Validates that a user may activate a store as their tenant context.
 *
 * Authorization is delegated to StorePolicy@activate, the same membership
 * check EnsureTenantContext relies on for every subsequent request; this
 * action exists as an explicit endpoint so a client can confirm access and
 * fetch store details before switching context.
 */
final class ActivateStore
{
    public function handle(User $user, Store $store): Store
    {
        if (! Gate::forUser($user)->allows('activate', $store)) {
            throw TenantAccessDeniedException::forStore($store->id);
        }

        return $store;
    }
}
