<?php

namespace App\Domain\Financial\Models;

use App\Domain\Stores\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pure internal locking primitive for AllocateRefundNumber — never
 * exposed through any API, mirrors OrderNumberSequence/
 * ReturnNumberSequence exactly.
 *
 * @property string $store_id
 * @property int $next_number
 */
class RefundNumberSequence extends Model
{
    protected $table = 'refund_number_sequences';

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
