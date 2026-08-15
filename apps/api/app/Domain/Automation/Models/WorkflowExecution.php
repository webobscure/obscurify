<?php

namespace App\Domain\Automation\Models;

use App\Domain\Automation\Enums\WorkflowExecutionStatus;
use App\Domain\Stores\Models\Store;
use App\Shared\Commerce\Models\OutboxEvent;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $store_id
 * @property string $workflow_id
 * @property string $workflow_version_id
 * @property string $outbox_event_id
 * @property WorkflowExecutionStatus $status
 * @property array<string, mixed> $context
 * @property int $current_action_position
 * @property int $depth
 * @property string|null $root_execution_id
 * @property string|null $caused_by_execution_id
 * @property int $attempts
 * @property Carbon|null $next_retry_at
 * @property Carbon|null $next_resume_at
 * @property string|null $wait_until_event_type
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property string|null $error_message
 */
class WorkflowExecution extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'workflow_id',
        'workflow_version_id',
        'outbox_event_id',
        'status',
        'context',
        'current_action_position',
        'depth',
        'root_execution_id',
        'caused_by_execution_id',
        'attempts',
        'next_retry_at',
        'next_resume_at',
        'wait_until_event_type',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkflowExecutionStatus::class,
            'context' => 'array',
            'current_action_position' => 'integer',
            'depth' => 'integer',
            'attempts' => 'integer',
            'next_retry_at' => 'datetime',
            'next_resume_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * @return BelongsTo<WorkflowVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    /**
     * @return BelongsTo<OutboxEvent, $this>
     */
    public function outboxEvent(): BelongsTo
    {
        return $this->belongsTo(OutboxEvent::class);
    }

    /**
     * @return HasMany<WorkflowExecutionStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowExecutionStep::class)->orderBy('position');
    }
}
