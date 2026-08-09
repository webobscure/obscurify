<?php

namespace App\Domain\Shipping\Models;

use App\Domain\Stores\Models\Store;
use Database\Factories\ShippingWebhookEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Deliberately does NOT use BelongsToTenant: a webhook arrives with no
 * TenantContext at all, so `store_id` can't be auto-injected from a
 * tenant that isn't known yet — mirrors PaymentWebhookEvent exactly, see
 * that model's docblock.
 *
 * @property string $id
 * @property string|null $store_id
 * @property string $provider
 * @property string $external_event_id
 * @property string|null $external_shipment_id
 * @property string $event_type
 * @property string $payload_hash
 * @property Carbon|null $processed_at
 */
class ShippingWebhookEvent extends Model
{
    /** @use HasFactory<ShippingWebhookEventFactory> */
    use HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function newFactory(): ShippingWebhookEventFactory
    {
        return ShippingWebhookEventFactory::new();
    }

    protected $fillable = [
        'store_id',
        'provider',
        'external_event_id',
        'external_shipment_id',
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
