<?php

namespace App\Domain\Automation\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A minimal admin to-do item, created by the "Create task" automation
 * action — not a project-management feature.
 *
 * @property string $id
 * @property string $store_id
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property string|null $related_type
 * @property string|null $related_id
 * @property string|null $workflow_execution_id
 * @property Carbon|null $due_at
 * @property Carbon|null $completed_at
 */
class Task extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'title',
        'description',
        'status',
        'related_type',
        'related_id',
        'workflow_execution_id',
        'due_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
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
}
