<?php

namespace App\Domain\Analytics\Models;

use App\Domain\Analytics\Enums\ReportStatus;
use App\Domain\Analytics\Enums\ReportType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $store_id
 * @property string|null $saved_report_id
 * @property ReportType $report_type
 * @property array<string, mixed> $filters
 * @property list<string> $columns
 * @property ReportStatus $status
 * @property list<array<string, mixed>>|null $result
 * @property int|null $row_count
 * @property string|null $error_message
 * @property Carbon|null $generated_at
 */
class Report extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'saved_report_id',
        'report_type',
        'filters',
        'columns',
        'status',
        'result',
        'row_count',
        'error_message',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'report_type' => ReportType::class,
            'filters' => 'array',
            'columns' => 'array',
            'status' => ReportStatus::class,
            'result' => 'array',
            'row_count' => 'integer',
            'generated_at' => 'datetime',
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
     * @return BelongsTo<SavedReport, $this>
     */
    public function savedReport(): BelongsTo
    {
        return $this->belongsTo(SavedReport::class);
    }

    /**
     * @return HasMany<ReportExport, $this>
     */
    public function exports(): HasMany
    {
        return $this->hasMany(ReportExport::class);
    }
}
