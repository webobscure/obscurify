<?php

namespace App\Domain\Themes\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per store — the single pointer ThemeRenderer resolves first.
 *
 * @property string $id
 * @property string $store_id
 * @property string $theme_id
 * @property string $theme_version_id
 */
class ActiveTheme extends Model
{
    use BelongsToTenant, HasUlids;

    public $timestamps = false;

    protected $fillable = ['theme_id', 'theme_version_id', 'activated_at'];

    protected function casts(): array
    {
        return ['activated_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Theme, $this>
     */
    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    /**
     * @return BelongsTo<ThemeVersion, $this>
     */
    public function themeVersion(): BelongsTo
    {
        return $this->belongsTo(ThemeVersion::class);
    }
}
