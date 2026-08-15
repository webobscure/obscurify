<?php

namespace App\Domain\CustomerIntelligence\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\CustomerTagFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $store_id
 * @property string $name
 * @property string $slug
 * @property bool $is_system
 */
class CustomerTag extends Model
{
    /** @use HasFactory<CustomerTagFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): CustomerTagFactory
    {
        return CustomerTagFactory::new();
    }

    protected $fillable = [
        'name',
        'slug',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
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
     * @return HasMany<CustomerTagAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(CustomerTagAssignment::class);
    }
}
