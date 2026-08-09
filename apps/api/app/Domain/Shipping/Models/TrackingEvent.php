<?php

namespace App\Domain\Shipping\Models;

use App\Domain\Shipping\Enums\TrackingEventStatus;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\TrackingEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only — never updated or deleted once written (spec section 21).
 *
 * @property string $id
 * @property string $store_id
 * @property string $shipment_id
 * @property TrackingEventStatus $status
 * @property string|null $description
 * @property Carbon $occurred_at
 * @property string|null $location
 * @property array<string, mixed>|null $metadata
 */
class TrackingEvent extends Model
{
    /** @use HasFactory<TrackingEventFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function newFactory(): TrackingEventFactory
    {
        return TrackingEventFactory::new();
    }

    protected $fillable = [
        'shipment_id',
        'status',
        'description',
        'occurred_at',
        'location',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => TrackingEventStatus::class,
            'occurred_at' => 'datetime',
            'metadata' => 'array',
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
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
