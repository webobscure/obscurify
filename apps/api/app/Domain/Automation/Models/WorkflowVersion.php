<?php

namespace App\Domain\Automation\Models;

use App\Domain\Automation\Enums\WorkflowVersionStatus;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $store_id
 * @property string $workflow_id
 * @property int $version_number
 * @property WorkflowVersionStatus $status
 */
class WorkflowVersion extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'workflow_id',
        'version_number',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'status' => WorkflowVersionStatus::class,
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
     * @return HasOne<WorkflowTrigger, $this>
     */
    public function trigger(): HasOne
    {
        return $this->hasOne(WorkflowTrigger::class);
    }

    /**
     * Top-level condition nodes only — see WorkflowConditionTreeLoader
     * for eager-loading the whole tree.
     *
     * @return HasMany<WorkflowCondition, $this>
     */
    public function rootConditions(): HasMany
    {
        return $this->hasMany(WorkflowCondition::class)->whereNull('parent_id')->orderBy('position');
    }

    /**
     * @return HasMany<WorkflowAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(WorkflowAction::class)->orderBy('position');
    }
}
