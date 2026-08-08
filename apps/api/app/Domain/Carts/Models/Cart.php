<?php

namespace App\Domain\Carts\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $store_id
 * @property string|null $customer_id
 * @property string $token
 * @property string $currency
 * @property string $status
 * @property Carbon|null $expires_at
 */
class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): CartFactory
    {
        return CartFactory::new();
    }

    protected $fillable = [
        'customer_id',
        'token',
        'currency',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
