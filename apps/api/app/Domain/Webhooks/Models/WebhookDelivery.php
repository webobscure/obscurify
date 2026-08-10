<?php

namespace App\Domain\Webhooks\Models;

use App\Domain\Stores\Models\Store;
use App\Domain\Webhooks\Enums\WebhookDeliveryStatus;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per (WebhookSubscription, OutboxEvent) pair — the unique
 * constraint on the migration is the idempotency claim
 * DispatchWebhooksForEvent inserts against, so the same event can never
 * fan out twice to the same subscription even under concurrent
 * dispatch. Mutates across delivery attempts (see DeliverWebhookJob)
 * rather than an append-only attempts log — see
 * docs/architecture/webhooks.md for why.
 *
 * @property string $id
 * @property string $store_id
 * @property string $webhook_subscription_id
 * @property string $outbox_event_id
 * @property string $event_type
 * @property WebhookDeliveryStatus $status
 * @property int $attempt_count
 * @property int|null $response_code
 * @property string|null $error_message
 * @property Carbon|null $last_attempted_at
 * @property Carbon|null $next_retry_at
 * @property Carbon|null $delivered_at
 */
class WebhookDelivery extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'webhook_subscription_id',
        'outbox_event_id',
        'event_type',
        'status',
        'attempt_count',
        'response_code',
        'error_message',
        'last_attempted_at',
        'next_retry_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WebhookDeliveryStatus::class,
            'attempt_count' => 'integer',
            'response_code' => 'integer',
            'last_attempted_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'delivered_at' => 'datetime',
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
     * @return BelongsTo<WebhookSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'webhook_subscription_id');
    }
}
