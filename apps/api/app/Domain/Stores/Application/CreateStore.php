<?php

namespace App\Domain\Stores\Application;

use App\Domain\Stores\Enums\StoreStatus;
use App\Domain\Stores\Enums\StoreUserRole;
use App\Domain\Stores\Enums\StoreUserStatus;
use App\Domain\Stores\Models\Store;
use App\Domain\Stores\Models\StoreUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateStore
{
    /**
     * @param  array{name: string, slug: string, default_currency?: string, default_locale?: string, timezone?: string, settings?: array<string, mixed>|null}  $data
     */
    public function handle(User $creator, array $data): Store
    {
        return DB::transaction(function () use ($creator, $data) {
            $store = new Store($data);
            $store->owner_id = $creator->id;
            $store->status = StoreStatus::Active;
            $store->save();
            $store->refresh();

            $membership = new StoreUser([
                'role' => StoreUserRole::Owner,
                'status' => StoreUserStatus::Active,
            ]);
            $membership->store_id = $store->id;
            $membership->user_id = $creator->id;
            $membership->save();

            return $store;
        });
    }
}
