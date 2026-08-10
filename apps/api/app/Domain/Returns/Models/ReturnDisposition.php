<?php

namespace App\Domain\Returns\Models;

use App\Domain\Returns\Enums\ReturnDisposition as ReturnDispositionValue;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\ReturnDispositionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The post-inspection decision for one ReturnItem (spec section 7) —
 * chosen alongside inspection (InspectReturn) but only *applied* to
 * Inventory later, at CompleteReturn (`applied_at`, spec section 8:
 * "Inventory changes happen ONLY after inspection"). Enum imported under
 * an alias since this model and its backing enum share a short name by
 * design (same relationship as ReturnItem.reason/ReturnReason).
 *
 * @property string $id
 * @property string $store_id
 * @property string $return_item_id
 * @property ReturnDispositionValue $disposition
 * @property string|null $notes
 * @property string|null $decided_by
 * @property Carbon $decided_at
 * @property Carbon|null $applied_at
 */
class ReturnDisposition extends Model
{
    /** @use HasFactory<ReturnDispositionFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): ReturnDispositionFactory
    {
        return ReturnDispositionFactory::new();
    }

    protected $fillable = [
        'return_item_id',
        'disposition',
        'notes',
        'decided_by',
        'decided_at',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'disposition' => ReturnDispositionValue::class,
            'decided_at' => 'datetime',
            'applied_at' => 'datetime',
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
     * @return BelongsTo<ReturnItem, $this>
     */
    public function returnItem(): BelongsTo
    {
        return $this->belongsTo(ReturnItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
