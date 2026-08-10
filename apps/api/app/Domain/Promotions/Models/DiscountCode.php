<?php

namespace App\Domain\Promotions\Models;

use App\Domain\Promotions\Enums\DiscountCodeStatus;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\DiscountCodeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * `code` is always normalized to uppercase on write (see setCodeAttribute)
 * so lookup by DiscountCode::findByCode() stays a plain case-insensitive
 * match without a citext/lower() index.
 *
 * @property string $id
 * @property string $store_id
 * @property string $promotion_id
 * @property string $code
 * @property int|null $usage_limit
 * @property int|null $per_customer_limit
 * @property int $usage_count
 * @property Carbon|null $expires_at
 * @property DiscountCodeStatus $status
 */
class DiscountCode extends Model
{
    /** @use HasFactory<DiscountCodeFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): DiscountCodeFactory
    {
        return DiscountCodeFactory::new();
    }

    protected $fillable = [
        'promotion_id',
        'code',
        'usage_limit',
        'per_customer_limit',
        'usage_count',
        'expires_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'usage_limit' => 'integer',
            'per_customer_limit' => 'integer',
            'usage_count' => 'integer',
            'expires_at' => 'datetime',
            'status' => DiscountCodeStatus::class,
        ];
    }

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function hasUsesRemaining(): bool
    {
        return $this->usage_limit === null || $this->usage_count < $this->usage_limit;
    }

    public static function findByCode(string $code): ?self
    {
        return static::query()->where('code', strtoupper(trim($code)))->first();
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Promotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
