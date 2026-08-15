<?php

namespace App\Domain\Search\Models;

use App\Domain\Search\Enums\SearchSuggestionType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A materialized autocomplete candidate — see the migration's own
 * docblock. `reference_id` points at the Product/Collection/Category
 * row for non-query types; null for a plain popular-query term.
 *
 * @property string $id
 * @property string $store_id
 * @property string $term
 * @property SearchSuggestionType $type
 * @property string|null $reference_id
 * @property int $score
 * @property bool $is_active
 */
class SearchSuggestion extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'term',
        'type',
        'reference_id',
        'score',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => SearchSuggestionType::class,
            'score' => 'integer',
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
