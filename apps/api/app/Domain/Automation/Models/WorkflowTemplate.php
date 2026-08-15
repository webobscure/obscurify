<?php

namespace App\Domain\Automation\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A global, platform-wide catalog row (not tenant-scoped — see the
 * migration). `definition` is {trigger: {event_type}, conditions: [...],
 * actions: [...]} — the exact shape InstantiateWorkflowFromTemplate
 * reads to create a real, store-owned Workflow.
 *
 * @property string $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property string|null $category
 * @property array<string, mixed> $definition
 */
class WorkflowTemplate extends Model
{
    use HasUlids;

    protected $fillable = [
        'key',
        'name',
        'description',
        'category',
        'definition',
    ];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
        ];
    }
}
