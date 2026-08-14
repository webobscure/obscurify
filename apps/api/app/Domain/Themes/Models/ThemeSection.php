<?php

namespace App\Domain\Themes\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A section TYPE definition (e.g. "hero") — see the migration's
 * docblock for how this differs from a section *instance* (which lives
 * in ThemeTemplate.sections).
 *
 * @property string $id
 * @property string $store_id
 * @property string $theme_version_id
 * @property string $handle
 * @property string $name
 * @property array<int, array<string, mixed>> $schema
 * @property array<string, mixed>|null $presets
 */
class ThemeSection extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['theme_version_id', 'handle', 'name', 'schema', 'presets'];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'presets' => 'array',
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
     * @return BelongsTo<ThemeVersion, $this>
     */
    public function themeVersion(): BelongsTo
    {
        return $this->belongsTo(ThemeVersion::class);
    }

    /**
     * @return HasMany<ThemeBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(ThemeBlock::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultSettings(): array
    {
        $defaults = [];

        foreach ($this->schema as $field) {
            if (isset($field['id'])) {
                $defaults[$field['id']] = $field['default'] ?? null;
            }
        }

        return $defaults;
    }
}
