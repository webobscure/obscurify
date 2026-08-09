<?php

namespace App\Domain\Fulfillment\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\FulfillmentEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only timeline (spec section 17) — never updated or deleted once
 * written, mirrors TrackingEvent.
 *
 * @property string $id
 * @property string $store_id
 * @property string $fulfillment_id
 * @property string $type
 * @property string|null $description
 * @property Carbon $occurred_at
 */
class FulfillmentEvent extends Model
{
    /** @use HasFactory<FulfillmentEventFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function newFactory(): FulfillmentEventFactory
    {
        return FulfillmentEventFactory::new();
    }

    protected $fillable = [
        'fulfillment_id',
        'type',
        'description',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
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
     * @return BelongsTo<Fulfillment, $this>
     */
    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }
}
