<?php

namespace App\Domain\Automation\Models;

use App\Domain\Automation\Enums\WorkflowExecutionStepStatus;
use App\Domain\Automation\Enums\WorkflowExecutionStepType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $store_id
 * @property string $workflow_execution_id
 * @property string|null $workflow_action_id
 * @property WorkflowExecutionStepType $step_type
 * @property WorkflowExecutionStepStatus $status
 * @property array<string, mixed>|null $input
 * @property array<string, mixed>|null $output
 * @property string|null $error_message
 * @property int $attempts
 * @property int $position
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
class WorkflowExecutionStep extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'workflow_execution_id',
        'workflow_action_id',
        'step_type',
        'status',
        'input',
        'output',
        'error_message',
        'attempts',
        'position',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'step_type' => WorkflowExecutionStepType::class,
            'status' => WorkflowExecutionStepStatus::class,
            'input' => 'array',
            'output' => 'array',
            'attempts' => 'integer',
            'position' => 'integer',
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
     * @return BelongsTo<WorkflowExecution, $this>
     */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(WorkflowExecution::class, 'workflow_execution_id');
    }

    /**
     * @return BelongsTo<WorkflowAction, $this>
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(WorkflowAction::class, 'workflow_action_id');
    }
}
