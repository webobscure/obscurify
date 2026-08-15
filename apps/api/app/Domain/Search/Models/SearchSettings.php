<?php

namespace App\Domain\Search\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per store — global search behavior toggles + which
 * SearchProvider is active. `typo_tolerance_enabled`/`synonyms_enabled`/
 * `facets_enabled` are read by ExecuteSearch to turn whole pipeline
 * stages on/off without code changes.
 *
 * @property string $id
 * @property string $store_id
 * @property string|null $active_provider_id
 * @property int $results_per_page
 * @property int $autocomplete_limit
 * @property bool $typo_tolerance_enabled
 * @property bool $synonyms_enabled
 * @property bool $facets_enabled
 * @property-read SearchProvider|null $activeProvider
 */
class SearchSettings extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'active_provider_id',
        'results_per_page',
        'autocomplete_limit',
        'typo_tolerance_enabled',
        'synonyms_enabled',
        'facets_enabled',
    ];

    protected function casts(): array
    {
        return [
            'results_per_page' => 'integer',
            'autocomplete_limit' => 'integer',
            'typo_tolerance_enabled' => 'boolean',
            'synonyms_enabled' => 'boolean',
            'facets_enabled' => 'boolean',
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
     * @return BelongsTo<SearchProvider, $this>
     */
    public function activeProvider(): BelongsTo
    {
        return $this->belongsTo(SearchProvider::class, 'active_provider_id');
    }

    /**
     * Checks the `active_provider_id` foreign key directly rather than
     * chaining `?->` off the `activeProvider` relation itself — Eloquent's
     * BelongsTo return type doesn't encode the FK's actual nullability, so
     * a relation-object nullsafe chain reads as dead code to static
     * analysis even though `active_provider_id` is genuinely nullable.
     */
    public function activeProviderCode(): string
    {
        return $this->active_provider_id !== null ? $this->activeProvider->code : SearchProvider::DATABASE;
    }
}
