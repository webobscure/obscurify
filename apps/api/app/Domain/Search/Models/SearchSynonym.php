<?php

namespace App\Domain\Search\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `term` -> `synonyms[]` (e.g. "tv" -> ["television"]). When
 * `is_bidirectional`, SynonymExpander also expands each synonym back to
 * `term` (so "television" -> "tv" too), matching the spec's own
 * "iphone -> iPhone" example, which reads naturally both ways.
 *
 * @property string $id
 * @property string $store_id
 * @property string $term
 * @property array<int, string> $synonyms
 * @property bool $is_bidirectional
 * @property string|null $locale
 * @property bool $is_active
 */
class SearchSynonym extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'term',
        'synonyms',
        'is_bidirectional',
        'locale',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'synonyms' => 'array',
            'is_bidirectional' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
