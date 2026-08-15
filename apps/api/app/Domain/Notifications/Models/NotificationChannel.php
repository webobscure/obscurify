<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\Enums\NotificationChannelType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A store's per-channel configuration: which NotificationProvider row
 * currently handles this channel, and whether the channel is enabled
 * at all. One row per (store, channel) — see EnsureDefaultNotificationSetup
 * for how these are seeded.
 *
 * @property string $id
 * @property string $store_id
 * @property NotificationChannelType $channel
 * @property string|null $provider_id
 * @property bool $is_enabled
 */
class NotificationChannel extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'channel',
        'provider_id',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannelType::class,
            'is_enabled' => 'boolean',
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
     * @return BelongsTo<NotificationProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(NotificationProvider::class, 'provider_id');
    }
}
