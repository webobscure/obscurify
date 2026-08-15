<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\Enums\NotificationChannelType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Routes one Platform Event `event_type` (on one channel) to a
 * NotificationTemplate — the notification-domain sibling of
 * WebhookSubscription and WorkflowTrigger. Matched by
 * DispatchNotificationsForEvent, the 4th ProcessOutboxEventsCommand
 * subscriber (spec section 7: "Notifications may originate from
 * Platform Events").
 *
 * @property string $id
 * @property string $store_id
 * @property string $event_type
 * @property NotificationChannelType $channel
 * @property string $template_id
 * @property bool $is_enabled
 */
class NotificationEvent extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'event_type',
        'channel',
        'template_id',
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
     * @return BelongsTo<NotificationTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }
}
