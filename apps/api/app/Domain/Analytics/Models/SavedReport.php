<?php

namespace App\Domain\Analytics\Models;

use App\Domain\Analytics\Enums\ReportType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $store_id
 * @property string $name
 * @property ReportType $report_type
 * @property array<string, mixed> $filters
 * @property list<string> $columns
 */
class SavedReport extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'name',
        'report_type',
        'filters',
        'columns',
    ];

    protected function casts(): array
    {
        return [
            'report_type' => ReportType::class,
            'filters' => 'array',
            'columns' => 'array',
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
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
