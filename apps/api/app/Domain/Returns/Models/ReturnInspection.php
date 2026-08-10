<?php

namespace App\Domain\Returns\Models;

use App\Domain\Returns\Enums\ReturnCondition;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\ReturnInspectionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Write-once verified assessment of a returned item (spec section 6) —
 * never updated after creation, mirrors ReturnEvent/TrackingEvent's
 * append-only discipline in spirit, though this is a single record per
 * ReturnItem rather than a growing list (one physical inspection, one
 * verdict).
 *
 * @property string $id
 * @property string $store_id
 * @property string $return_item_id
 * @property ReturnCondition $condition
 * @property array<int, mixed>|null $photos
 * @property string|null $notes
 * @property string|null $inspected_by
 * @property Carbon $inspected_at
 */
class ReturnInspection extends Model
{
    /** @use HasFactory<ReturnInspectionFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function newFactory(): ReturnInspectionFactory
    {
        return ReturnInspectionFactory::new();
    }

    protected $fillable = [
        'return_item_id',
        'condition',
        'photos',
        'notes',
        'inspected_by',
        'inspected_at',
    ];

    protected function casts(): array
    {
        return [
            'condition' => ReturnCondition::class,
            'photos' => 'array',
            'inspected_at' => 'datetime',
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
    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }
}
