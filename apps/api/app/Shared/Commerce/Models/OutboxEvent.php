<?php

namespace App\Shared\Commerce\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\OutboxEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Minimal transactional outbox — written in the same DB transaction as
 * the business change it describes, processed asynchronously afterward.
 * See ProcessOutboxEventsCommand.
 *
 * @property string $id
 * @property string $store_id
 * @property string $event_type
 * @property string $aggregate_type
 * @property string $aggregate_id
 * @property array<string, mixed> $payload
 * @property Carbon|null $occurred_at
 * @property Carbon|null $processed_at
 * @property int $attempts
 * @property string|null $caused_by_workflow_execution_id
 */
class OutboxEvent extends Model
{
    /** @use HasFactory<OutboxEventFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    public $timestamps = false;

    protected static function newFactory(): OutboxEventFactory
    {
        return OutboxEventFactory::new();
    }

    protected $fillable = [
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'payload',
        'occurred_at',
        'processed_at',
        'attempts',
        'caused_by_workflow_execution_id',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
            'attempts' => 'integer',
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
