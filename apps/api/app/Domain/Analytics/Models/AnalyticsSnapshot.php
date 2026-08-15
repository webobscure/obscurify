<?php

namespace App\Domain\Analytics\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One (store, metric, day) aggregate row — see the migration and
 * docs/architecture/analytics.md §4/§8.
 *
 * @property string $id
 * @property string $store_id
 * @property string $metric_key
 * @property Carbon $period_date
 * @property int|null $value
 * @property int|null $count
 * @property array<string, array{label: string, value: int}>|null $breakdown
 * @property Carbon $computed_at
 */
class AnalyticsSnapshot extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'metric_key',
        'period_date',
        'value',
        'count',
        'breakdown',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'period_date' => 'date',
            'value' => 'integer',
            'count' => 'integer',
            'breakdown' => 'array',
            'computed_at' => 'datetime',
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
