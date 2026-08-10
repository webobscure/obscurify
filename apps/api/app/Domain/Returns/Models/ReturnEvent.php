<?php

namespace App\Domain\Returns\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\ReturnEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only timeline (spec section 10) — never updated or deleted once
 * written, mirrors FulfillmentEvent/TrackingEvent exactly.
 *
 * @property string $id
 * @property string $store_id
 * @property string $return_request_id
 * @property string $type
 * @property string|null $description
 * @property Carbon $occurred_at
 */
class ReturnEvent extends Model
{
    /** @use HasFactory<ReturnEventFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function newFactory(): ReturnEventFactory
    {
        return ReturnEventFactory::new();
    }

    protected $fillable = [
        'return_request_id',
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
     * @return BelongsTo<ReturnRequest, $this>
     */
    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }
}
