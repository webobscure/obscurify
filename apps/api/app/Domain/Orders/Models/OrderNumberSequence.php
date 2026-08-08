<?php

namespace App\Domain\Orders\Models;

use App\Domain\Stores\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pure internal locking primitive for AllocateOrderNumber — never exposed
 * through any API, so it deliberately skips BelongsToTenant (its primary
 * key already *is* store_id; every query targets one explicitly).
 *
 * @property string $store_id
 * @property int $next_number
 */
class OrderNumberSequence extends Model
{
    protected $table = 'order_number_sequences';

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
