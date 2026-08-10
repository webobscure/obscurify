<?php

namespace App\Domain\Financial\Models;

use App\Domain\Stores\Models\Store;
use Database\Factories\RefundWebhookEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Deliberately does NOT use BelongsToTenant — mirrors PaymentWebhookEvent
 * exactly, same reasoning: a webhook arrives with no TenantContext at
 * all, so `store_id` can't be auto-injected from a tenant that isn't
 * known yet. The (provider, external_event_id) unique index is what
 * claims/deduplicates a delivery before we even know which store it
 * belongs to — see ProcessRefundWebhook.
 *
 * @property string $id
 * @property string|null $store_id
 * @property string $provider
 * @property string $external_event_id
 * @property string|null $external_refund_id
 * @property string $event_type
 * @property string $payload_hash
 * @property Carbon|null $processed_at
 */
class RefundWebhookEvent extends Model
{
    /** @use HasFactory<RefundWebhookEventFactory> */
    use HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function newFactory(): RefundWebhookEventFactory
    {
        return RefundWebhookEventFactory::new();
    }

    protected $fillable = [
        'store_id',
        'provider',
        'external_event_id',
        'external_refund_id',
        'event_type',
        'payload_hash',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
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
