<?php

namespace App\Domain\Returns\Models;

use App\Domain\Stores\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pure internal locking primitive for AllocateReturnNumber — never exposed
 * through any API, mirrors OrderNumberSequence exactly (its primary key
 * already *is* store_id, so it deliberately skips BelongsToTenant).
 *
 * @property string $store_id
 * @property int $next_number
 */
class ReturnNumberSequence extends Model
{
    protected $table = 'return_number_sequences';

    protected $primaryKey = 'store_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'next_number',
    ];

    protected function casts(): array
    {
        return [
            'next_number' => 'integer',
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
