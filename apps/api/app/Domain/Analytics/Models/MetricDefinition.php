<?php

namespace App\Domain\Analytics\Models;

use App\Domain\Analytics\Enums\MetricCalculation;
use App\Domain\Analytics\Enums\MetricCategory;
use App\Domain\Analytics\Enums\MetricUnit;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A global, platform-wide catalog row (not tenant-scoped — same
 * reasoning as Milestone 19's WorkflowVariable/WorkflowTemplate).
 * Seeded once by RegisterBuiltInAnalyticsCatalog.
 *
 * @property string $id
 * @property string $key
 * @property string $label
 * @property string|null $description
 * @property MetricCategory $category
 * @property MetricUnit $unit
 * @property MetricCalculation $calculation
 */
class MetricDefinition extends Model
{
    use HasUlids;

    protected $fillable = [
        'key',
        'label',
        'description',
        'category',
        'unit',
        'calculation',
    ];

    protected function casts(): array
    {
        return [
            'category' => MetricCategory::class,
            'unit' => MetricUnit::class,
            'calculation' => MetricCalculation::class,
        ];
    }
}
