<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Automation\Models\WorkflowExecution;
use App\Domain\Notifications\Enums\NotificationChannelType;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Notifications\Enums\NotificationTriggerSource;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One rendered message on one channel, fanned out to one or more
 * recipients (each with its own NotificationDelivery attempt chain).
 * `related_type`/`related_id` follows the exact polymorphic-by-string
 * convention InternalNotification/Task already used.
 *
 * @property string $id
 * @property string $store_id
 * @property string|null $template_id
 * @property NotificationChannelType $channel
 * @property string|null $event_type
 * @property string|null $subject
 * @property string $body_text
 * @property string|null $body_html
 * @property string|null $related_type
 * @property string|null $related_id
 * @property string|null $workflow_execution_id
 * @property NotificationTriggerSource $triggered_by
 * @property NotificationStatus $status
 */
class Notification extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'template_id',
        'channel',
        'event_type',
        'subject',
        'body_text',
        'body_html',
        'related_type',
        'related_id',
        'workflow_execution_id',
        'triggered_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannelType::class,
            'triggered_by' => NotificationTriggerSource::class,
            'status' => NotificationStatus::class,
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

    /**
     * @return BelongsTo<WorkflowExecution, $this>
     */
    public function workflowExecution(): BelongsTo
    {
        return $this->belongsTo(WorkflowExecution::class);
    }

    /**
     * @return HasMany<NotificationRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    /**
     * @return HasMany<NotificationDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }
}
