<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\Enums\NotificationChannelType;
use App\Domain\Notifications\Enums\NotificationDeliveryStatus;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One (notification, recipient) delivery attempt chain — mirrors
 * WebhookDelivery's own attempt_count/next_retry_at retry bookkeeping
 * exactly (see SendNotificationDeliveryJob).
 *
 * @property string $id
 * @property string $store_id
 * @property string $notification_id
 * @property string $recipient_id
 * @property NotificationChannelType $channel
 * @property string|null $provider_id
 * @property NotificationDeliveryStatus $status
 * @property int $attempt_count
 * @property Carbon|null $last_attempted_at
 * @property Carbon|null $next_retry_at
 * @property Carbon|null $delivered_at
 * @property array<string, mixed>|null $response_meta
 * @property string|null $error_message
 * @property string $idempotency_key
 */
class NotificationDelivery extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'notification_id',
        'recipient_id',
        'channel',
        'provider_id',
        'status',
        'attempt_count',
        'last_attempted_at',
        'next_retry_at',
        'delivered_at',
        'response_meta',
        'error_message',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannelType::class,
            'status' => NotificationDeliveryStatus::class,
            'attempt_count' => 'integer',
            'last_attempted_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'delivered_at' => 'datetime',
            'response_meta' => 'array',
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
     * @return BelongsTo<Notification, $this>
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    /**
     * @return BelongsTo<NotificationRecipient, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(NotificationRecipient::class, 'recipient_id');
    }

    /**
     * @return BelongsTo<NotificationProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(NotificationProvider::class, 'provider_id');
    }
}
