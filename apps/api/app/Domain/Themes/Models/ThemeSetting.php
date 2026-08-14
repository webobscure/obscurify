<?php

namespace App\Domain\Themes\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One global theme setting (spec section 7: Logo, Colors, Typography,
 * Buttons, Spacing, Radius, Container Width, Animations, Social Links,
 * Favicon), one row per key.
 *
 * @property string $id
 * @property string $store_id
 * @property string $theme_version_id
 * @property string $key
 * @property mixed $value
 */
class ThemeSetting extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['theme_version_id', 'key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<ThemeVersion, $this>
     */
    public function themeVersion(): BelongsTo
    {
        return $this->belongsTo(ThemeVersion::class);
    }
}
