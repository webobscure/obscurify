<?php

namespace App\Domain\Analytics\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Commerce\Models\OutboxEvent;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Analytics' own append-only, normalized event log — see the migration
 * and docs/architecture/analytics.md §2.
 *
 * @property string $id
 * @property string $store_id
 * @property string $outbox_event_id
 * @property string $event_type
 * @property Carbon $occurred_at
 * @property string $aggregate_type
 * @property string $aggregate_id
 * @property string|null $customer_id
 * @property int|null $amount
 * @property string|null $currency
 * @property array<string, mixed>|null $payload
 */
class AnalyticsEvent extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'outbox_event_id',
        'event_type',
        'occurred_at',
        'aggregate_type',
        'aggregate_id',
        'customer_id',
        'amount',
        'currency',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'amount' => 'integer',
            'payload' => 'array',
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
     * @return BelongsTo<OutboxEvent, $this>
     */
    public function outboxEvent(): BelongsTo
    {
        return $this->belongsTo(OutboxEvent::class);
    }
}
