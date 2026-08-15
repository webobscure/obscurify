<?php

namespace App\Domain\Automation\Models;

use App\Domain\Automation\Enums\WorkflowVariableSource;
use App\Domain\Automation\Enums\WorkflowVariableType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A global, platform-wide catalog row (not tenant-scoped — see the
 * migration). Built-ins only; app-contributed variables live on
 * AppExtension instead (ExtensionPoint::AutomationVariable) — see
 * WorkflowVariableRegistry, which merges both.
 *
 * @property string $id
 * @property WorkflowVariableSource $source
 * @property string $key
 * @property string $label
 * @property WorkflowVariableType $type
 * @property string[]|null $event_types
 */
class WorkflowVariable extends Model
{
    use HasUlids;

    protected $fillable = [
        'source',
        'key',
        'label',
        'type',
        'event_types',
    ];

    protected function casts(): array
    {
        return [
            'source' => WorkflowVariableSource::class,
            'type' => WorkflowVariableType::class,
            'event_types' => 'array',
        ];
    }
}
