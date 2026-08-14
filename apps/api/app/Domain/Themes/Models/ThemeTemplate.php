<?php

namespace App\Domain\Themes\Models;

use App\Domain\Stores\Models\Store;
use App\Domain\Themes\Enums\ThemeTemplateType;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One fixed template slot per version — `sections` is the ordered list
 * of section *instances* placed on it. See the migration's docblock for
 * the exact shape.
 *
 * @property string $id
 * @property string $store_id
 * @property string $theme_version_id
 * @property ThemeTemplateType $type
 * @property string $name
 * @property array<int, array<string, mixed>> $sections
 */
class ThemeTemplate extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['theme_version_id', 'type', 'name', 'sections'];

    protected function casts(): array
    {
        return [
            'type' => ThemeTemplateType::class,
            'sections' => 'array',
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
}
