<?php

namespace App\Domain\Analytics\Models;

use App\Domain\Analytics\Enums\ExportFormat;
use App\Domain\Analytics\Enums\ExportRecurrence;
use App\Domain\Analytics\Enums\ExportStatus;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $store_id
 * @property string $report_id
 * @property ExportFormat $format
 * @property ExportStatus $status
 * @property string|null $file_path
 * @property int|null $file_size
 * @property Carbon|null $scheduled_at
 * @property ExportRecurrence|null $recurrence
 * @property Carbon|null $completed_at
 */
class ReportExport extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'report_id',
        'format',
        'status',
        'file_path',
        'file_size',
        'scheduled_at',
        'recurrence',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'format' => ExportFormat::class,
            'status' => ExportStatus::class,
            'file_size' => 'integer',
            'scheduled_at' => 'datetime',
            'recurrence' => ExportRecurrence::class,
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

    /**
     * @return BelongsTo<Report, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
