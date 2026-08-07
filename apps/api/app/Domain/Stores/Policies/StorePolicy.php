<?php

namespace App\Domain\Stores\Policies;

use App\Domain\Stores\Enums\StoreUserStatus;
use App\Domain\Stores\Models\Store;
use App\Domain\Stores\Models\StoreUser;
use App\Models\User;

final class StorePolicy
{
    /**
     * A user may view/activate a store only if they hold an active
     * membership in it. This is the same check EnsureTenantContext
     * performs on every subsequent tenant-scoped request.
     */
    public function view(User $user, Store $store): bool
    {
        return StoreUser::query()
            ->where('store_id', $store->id)
            ->where('user_id', $user->id)
            ->where('status', StoreUserStatus::Active)
            ->exists();
    }

    public function activate(User $user, Store $store): bool
    {
        return $this->view($user, $store);
    }
}
