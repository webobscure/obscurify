<?php

namespace App\Domain\Apps\Models;

use App\Domain\Apps\Enums\InstalledAppStatus;
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
 * @property string $app_id
 * @property InstalledAppStatus $status
 * @property Carbon $installed_at
 * @property Carbon|null $uninstalled_at
 */
class InstalledApp extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'app_id',
        'status',
        'installed_at',
        'uninstalled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InstalledAppStatus::class,
            'installed_at' => 'datetime',
            'uninstalled_at' => 'datetime',
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
     * @return BelongsTo<App, $this>
     */
    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    /**
     * @return HasMany<AppPermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(AppPermission::class);
    }

    /**
     * @return HasMany<AppToken, $this>
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(AppToken::class);
    }

    /**
     * @return HasMany<AppSetting, $this>
     */
    public function settings(): HasMany
    {
        return $this->hasMany(AppSetting::class);
    }

    /**
     * @return HasMany<AppExtension, $this>
     */
    public function extensions(): HasMany
    {
        return $this->hasMany(AppExtension::class);
    }

    /**
     * @return string[]
     */
    public function activeScopes(): array
    {
        return $this->permissions()->whereNull('revoked_at')->pluck('scope')->all();
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->activeScopes(), true);
    }
}
