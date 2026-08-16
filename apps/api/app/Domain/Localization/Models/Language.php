<?php

namespace App\Domain\Localization\Models;

use Database\Factories\LanguageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Platform-wide catalog — no BelongsToTenant, the same shared-reference
 * status as a currency code (spec section 2: "Allow adding more
 * languages later. No hardcoded language checks").
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string $native_name
 * @property bool $is_active
 * @property int $sort_order
 */
class Language extends Model
{
    /** @use HasFactory<LanguageFactory> */
    use HasFactory, HasUlids;

    protected static function newFactory(): LanguageFactory
    {
        return LanguageFactory::new();
    }

    protected $fillable = [
        'code',
        'name',
        'native_name',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Locale, $this>
     */
    public function locales(): HasMany
    {
        return $this->hasMany(Locale::class, 'language_code', 'code');
    }
}
