<?php

namespace App\Domain\Automation\Models;

use App\Domain\Automation\Enums\WorkflowActionType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $store_id
 * @property string $workflow_version_id
 * @property WorkflowActionType $type
 * @property array<string, mixed> $config
 * @property int $position
 */
class WorkflowAction extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'workflow_version_id',
        'type',
        'config',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'type' => WorkflowActionType::class,
            'config' => 'array',
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
     * @return BelongsTo<WorkflowVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }
}
