<?php

namespace App\Domain\Localization\Models;

use App\Domain\Stores\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Spec section 8's "Supported languages" — see this table's own
 * migration docblock.
 *
 * @property string $id
 * @property string $store_id
 * @property string $locale_code
 */
class StoreSupportedLocale extends Model
{
    use HasUlids;

    protected $fillable = [
        'store_id',
        'locale_code',
    ];

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Locale, $this>
     */
    public function locale(): BelongsTo
    {
        return $this->belongsTo(Locale::class, 'locale_code', 'code');
    }
}
