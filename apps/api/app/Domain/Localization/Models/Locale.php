<?php

namespace App\Domain\Localization\Models;

use Database\Factories\LocaleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Platform-wide catalog — distinct from Language (see that model's own
 * docblock). `is_default` should have exactly one true row across the
 * whole table, enforced in Application layer (SetDefaultLanguage), the
 * same "exactly one active X" convention as
 * FiscalizationSettings.active_provider_id.
 *
 * @property string $id
 * @property string $code
 * @property string $language_code
 * @property string|null $fallback_locale_code
 * @property bool $is_default
 * @property bool $is_active
 */
class Locale extends Model
{
    /** @use HasFactory<LocaleFactory> */
    use HasFactory, HasUlids;

    protected static function newFactory(): LocaleFactory
    {
        return LocaleFactory::new();
    }

    protected $fillable = [
        'code',
        'language_code',
        'fallback_locale_code',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'language_code', 'code');
    }

    /**
     * @return BelongsTo<Locale, $this>
     */
    public function fallbackLocale(): BelongsTo
    {
        return $this->belongsTo(self::class, 'fallback_locale_code', 'code');
    }
}
