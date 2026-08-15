<?php

namespace App\Domain\Analytics\Models;

use App\Domain\Analytics\Enums\DashboardWidgetType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $store_id
 * @property string $dashboard_id
 * @property DashboardWidgetType $type
 * @property string $title
 * @property array<string, mixed> $config
 * @property int $position
 */
class DashboardWidget extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'dashboard_id',
        'type',
        'title',
        'config',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'type' => DashboardWidgetType::class,
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
     * @return BelongsTo<Dashboard, $this>
     */
    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }
}
