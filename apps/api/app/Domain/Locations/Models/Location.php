<?php

namespace App\Domain\Locations\Models;

use App\Domain\Locations\Enums\LocationStatus;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $store_id
 * @property string $name
 * @property LocationStatus $status
 * @property string|null $country
 * @property string|null $region
 * @property string|null $city
 * @property string|null $address
 */
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): LocationFactory
    {
        return LocationFactory::new();
    }

    protected $fillable = [
        'name',
        'status',
        'country',
        'region',
        'city',
        'address',
    ];

    protected function casts(): array
    {
        return [
            'status' => LocationStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
