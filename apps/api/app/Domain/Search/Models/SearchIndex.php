<?php

namespace App\Domain\Search\Models;

use App\Domain\Search\Enums\SearchIndexStatus;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $store_id
 * @property SearchIndexStatus $status
 * @property int $document_count
 * @property Carbon|null $last_full_reindex_at
 * @property Carbon|null $last_indexed_at
 * @property string|null $error_message
 */
class SearchIndex extends Model
{
    use BelongsToTenant, HasUlids;

    // Eloquent's pluralizer guesses "search_indices" for "SearchIndex" —
    // the migration (and every other reference in this domain) uses the
    // plain "search_indexes" spelling instead.
    protected $table = 'search_indexes';

    protected $fillable = [
        'status',
        'document_count',
        'last_full_reindex_at',
        'last_indexed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => SearchIndexStatus::class,
            'document_count' => 'integer',
            'last_full_reindex_at' => 'datetime',
            'last_indexed_at' => 'datetime',
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
