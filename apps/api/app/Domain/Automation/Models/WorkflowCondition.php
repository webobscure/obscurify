<?php

namespace App\Domain\Automation\Models;

use App\Domain\Automation\Enums\WorkflowConditionBoolean;
use App\Domain\Automation\Enums\WorkflowConditionOperator;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tree node — a *condition* leaf (`variable_key`/`operator`/`value` set)
 * or a *group* (`boolean_operator` set, has `children`). See
 * docs/architecture/automation.md §4.
 *
 * @property string $id
 * @property string $store_id
 * @property string $workflow_version_id
 * @property string|null $parent_id
 * @property WorkflowConditionBoolean|null $boolean_operator
 * @property string|null $variable_key
 * @property WorkflowConditionOperator|null $operator
 * @property mixed $value
 * @property int $position
 */
class WorkflowCondition extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'workflow_version_id',
        'parent_id',
        'boolean_operator',
        'variable_key',
        'operator',
        'value',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'boolean_operator' => WorkflowConditionBoolean::class,
            'operator' => WorkflowConditionOperator::class,
            'value' => 'array',
            'position' => 'integer',
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
     * @return BelongsTo<WorkflowCondition, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<WorkflowCondition, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function isGroup(): bool
    {
        return $this->boolean_operator !== null;
    }
}
