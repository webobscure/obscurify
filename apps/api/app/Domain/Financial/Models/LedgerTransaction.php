<?php

namespace App\Domain\Financial\Models;

use App\Domain\Orders\Models\Order;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\LedgerTransactionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Immutable accounting record — never updated or deleted once written
 * (spec section 5). Groups a balanced set of LedgerEntry rows
 * (sum(debits) == sum(credits), enforced by PostLedgerEntries at write
 * time). `reference_type`/`reference_id` are polymorphic by convention
 * (string class name + ulid, same pattern InventoryMovement already
 * uses), not a real morphTo relation.
 *
 * @property string $id
 * @property string $store_id
 * @property string $order_id
 * @property string $reference_type
 * @property string $reference_id
 * @property string|null $description
 * @property Carbon $occurred_at
 */
class LedgerTransaction extends Model
{
    /** @use HasFactory<LedgerTransactionFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function newFactory(): LedgerTransactionFactory
    {
        return LedgerTransactionFactory::new();
    }

    protected $fillable = [
        'order_id',
        'reference_type',
        'reference_id',
        'description',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
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
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
