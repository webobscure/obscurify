<?php

namespace App\Domain\Stores\Models;

use App\Domain\Stores\Enums\StoreUserRole;
use App\Domain\Stores\Enums\StoreUserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $store_id
 * @property string $user_id
 * @property StoreUserRole $role
 * @property StoreUserStatus $status
 */
class StoreUser extends Model
{
    protected $table = 'store_users';

    protected $fillable = [
        'role',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'role' => StoreUserRole::class,
            'status' => StoreUserStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
