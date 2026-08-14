<?php

namespace App\Domain\Themes\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A block TYPE definition (e.g. "button"), scoped to the ThemeSection
 * type that allows it.
 *
 * @property string $id
 * @property string $store_id
 * @property string $theme_version_id
 * @property string $theme_section_id
 * @property string $handle
 * @property string $name
 * @property array<int, array<string, mixed>> $schema
 */
class ThemeBlock extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['theme_version_id', 'theme_section_id', 'handle', 'name', 'schema'];

    protected function casts(): array
    {
        return ['schema' => 'array'];
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
     * @return BelongsTo<ThemeSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(ThemeSection::class, 'theme_section_id');
    }
}
