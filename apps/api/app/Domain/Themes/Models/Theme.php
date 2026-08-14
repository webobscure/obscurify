<?php

namespace App\Domain\Themes\Models;

use App\Domain\Stores\Models\Store;
use App\Domain\Themes\Enums\ThemeStatus;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\ThemeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A theme "product" a store owns — the actual editable content lives on
 * its ThemeVersion rows. See docs/architecture/themes.md.
 *
 * @property string $id
 * @property string $store_id
 * @property string $name
 * @property string $slug
 * @property ThemeStatus $status
 */
class Theme extends Model
{
    /** @use HasFactory<ThemeFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): ThemeFactory
    {
        return ThemeFactory::new();
    }

    protected $fillable = ['name', 'slug', 'status'];

    protected function casts(): array
    {
        return ['status' => ThemeStatus::class];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<ThemeVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ThemeVersion::class);
    }

    /**
     * @return HasMany<ThemePreset, $this>
     */
    public function presets(): HasMany
    {
        return $this->hasMany(ThemePreset::class);
    }

    /**
     * @return HasOne<ActiveTheme, $this>
     */
    public function activePointer(): HasOne
    {
        return $this->hasOne(ActiveTheme::class, 'theme_id');
    }
}
