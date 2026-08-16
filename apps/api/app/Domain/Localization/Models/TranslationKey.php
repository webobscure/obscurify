<?php

namespace App\Domain\Localization\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per translatable key within a namespace (e.g. `auth.login.title`)
 * — populated by `php artisan translations:scan`, never hand-created
 * through an admin form this milestone.
 *
 * @property string $id
 * @property string $namespace_id
 * @property string $key
 * @property string|null $description
 */
class TranslationKey extends Model
{
    use HasUlids;

    protected $fillable = [
        'namespace_id',
        'key',
        'description',
    ];

    /**
     * @return BelongsTo<TranslationNamespace, $this>
     */
    public function namespace(): BelongsTo
    {
        return $this->belongsTo(TranslationNamespace::class, 'namespace_id');
    }

    /**
     * @return HasMany<Translation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class);
    }
}
