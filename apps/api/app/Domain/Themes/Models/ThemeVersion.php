<?php

namespace App\Domain\Themes\Models;

use App\Domain\Stores\Models\Store;
use App\Domain\Themes\Enums\ThemeVersionStatus;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * An immutable-once-published snapshot of a theme's content (spec
 * section 12). See the migration's docblock for the draft/publish/
 * rollback lifecycle.
 *
 * @property string $id
 * @property string $store_id
 * @property string $theme_id
 * @property string|null $created_from_version_id
 * @property int $version_number
 * @property ThemeVersionStatus $status
 * @property Carbon|null $published_at
 */
class ThemeVersion extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'theme_id',
        'created_from_version_id',
        'version_number',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'status' => ThemeVersionStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === ThemeVersionStatus::Draft;
    }

    /**
     * @throws ValidationException if this version is no longer a draft
     *                             (published versions are immutable — spec section 12).
     */
    public function assertEditable(): void
    {
        if (! $this->isDraft()) {
            throw ValidationException::withMessages(['theme_version' => 'A published theme version is immutable — edit the current draft instead.']);
        }
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
    public function createdFrom(): BelongsTo
    {
        return $this->belongsTo(ThemeVersion::class, 'created_from_version_id');
    }

    /**
     * @return HasMany<ThemeAsset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(ThemeAsset::class);
    }

    /**
     * @return HasMany<ThemeTemplate, $this>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(ThemeTemplate::class);
    }

    /**
     * @return HasMany<ThemeSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(ThemeSection::class);
    }

    /**
     * @return HasMany<ThemeBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(ThemeBlock::class);
    }

    /**
     * @return HasMany<ThemeSetting, $this>
     */
    public function settings(): HasMany
    {
        return $this->hasMany(ThemeSetting::class);
    }
}
