<?php

namespace App\Domain\Apps\Models;

use App\Domain\Apps\Enums\AppStatus;
use App\Domain\Apps\Enums\ExtensionPoint;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $store_id
 * @property string $installed_app_id
 * @property ExtensionPoint $extension_point
 * @property array<string, mixed> $config
 * @property AppStatus $status
 */
class AppExtension extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'installed_app_id',
        'extension_point',
        'config',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'extension_point' => ExtensionPoint::class,
            'config' => 'array',
            'status' => AppStatus::class,
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
     * @return BelongsTo<InstalledApp, $this>
     */
    public function installedApp(): BelongsTo
    {
        return $this->belongsTo(InstalledApp::class);
    }
}
