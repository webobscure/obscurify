<?php

namespace App\Domain\Localization\Models;

use App\Domain\Localization\Enums\TranslationSource;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A queryable INDEX row over the real runtime translation sources — see
 * this table's own migration docblock and
 * docs/architecture/localization.md "Decision 1". Never read by
 * `__()`/Vue I18n at request/render time; only by
 * `translations:scan`/`translations:missing`/`translations:unused` and
 * a future admin coverage view.
 *
 * @property string $id
 * @property string $translation_key_id
 * @property string $locale_code
 * @property string $value
 * @property TranslationSource $source
 */
class Translation extends Model
{
    use HasUlids;

    protected $fillable = [
        'translation_key_id',
        'locale_code',
        'value',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'source' => TranslationSource::class,
        ];
    }

    /**
     * @return BelongsTo<TranslationKey, $this>
     */
    public function key(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'translation_key_id');
    }

    /**
     * @return BelongsTo<Locale, $this>
     */
    public function locale(): BelongsTo
    {
        return $this->belongsTo(Locale::class, 'locale_code', 'code');
    }
}
