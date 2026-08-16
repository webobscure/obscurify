<?php

namespace App\Domain\Localization\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Platform-wide catalog — one row per top-level namespace (auth,
 * catalog, orders, payments, shipping, notifications, automation,
 * analytics, cms, themes, b2b, search, ... — spec section 3), matching
 * `lang/{locale}/{namespace}.php`'s own file layout 1:1.
 *
 * @property string $id
 * @property string $code
 * @property string|null $description
 */
class TranslationNamespace extends Model
{
    use HasUlids;

    protected $fillable = [
        'code',
        'description',
    ];

    /**
     * @return HasMany<TranslationKey, $this>
     */
    public function keys(): HasMany
    {
        return $this->hasMany(TranslationKey::class, 'namespace_id');
    }
}
