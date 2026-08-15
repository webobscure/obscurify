<?php

namespace App\Domain\Automation\Models;

use App\Domain\Automation\Enums\WorkflowStatus;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\WorkflowFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $store_id
 * @property string $name
 * @property string|null $description
 * @property WorkflowStatus $status
 * @property string|null $published_version_id
 */
class Workflow extends Model
{
    /** @use HasFactory<WorkflowFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): WorkflowFactory
    {
        return WorkflowFactory::new();
    }

    protected $fillable = [
        'name',
        'description',
        'status',
        'published_version_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkflowStatus::class,
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
     * @return HasMany<WorkflowVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class)->orderByDesc('version_number');
    }

    /**
     * @return BelongsTo<WorkflowVersion, $this>
     */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'published_version_id');
    }

    /**
     * @return HasMany<WorkflowExecution, $this>
     */
    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowExecution::class);
    }
}
