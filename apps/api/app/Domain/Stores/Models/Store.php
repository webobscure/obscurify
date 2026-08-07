<?php

namespace App\Domain\Stores\Models;

use App\Domain\Stores\Enums\StoreStatus;
use App\Models\User;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $owner_id
 * @property string $name
 * @property string $slug
 * @property StoreStatus $status
 * @property string $default_currency
 * @property string $default_locale
 * @property string $timezone
 * @property array<string, mixed>|null $settings
 */
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory, HasUlids;

    protected static function newFactory(): StoreFactory
    {
        return StoreFactory::new();
    }

    protected $fillable = [
        'name',
        'slug',
        'default_currency',
        'default_locale',
        'timezone',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'status' => StoreStatus::class,
            'settings' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<StoreUser, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(StoreUser::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_users')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }
}
