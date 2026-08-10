<?php

namespace App\Domain\Promotions\Models;

use App\Domain\Promotions\Enums\PromotionStackingMode;
use App\Domain\Promotions\Enums\PromotionStatus;
use App\Domain\Promotions\Enums\PromotionTriggerType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $store_id
 * @property string $name
 * @property string|null $description
 * @property PromotionTriggerType $trigger_type
 * @property PromotionStackingMode $stacking_mode
 * @property int $priority
 * @property PromotionStatus $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): PromotionFactory
    {
        return PromotionFactory::new();
    }

    protected $fillable = [
        'name',
        'description',
        'trigger_type',
        'stacking_mode',
        'priority',
        'status',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'trigger_type' => PromotionTriggerType::class,
            'stacking_mode' => PromotionStackingMode::class,
            'priority' => 'integer',
            'status' => PromotionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function isWithinDateRange(?Carbon $at = null): bool
    {
        $at ??= now();

        if ($this->starts_at !== null && $at->lt($this->starts_at)) {
            return false;
        }

        return $this->ends_at === null || ! $at->gt($this->ends_at);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<PromotionRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(PromotionRule::class);
    }

    /**
     * @return HasMany<PromotionAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(PromotionAction::class);
    }

    /**
     * @return HasMany<DiscountCode, $this>
     */
    public function discountCodes(): HasMany
    {
        return $this->hasMany(DiscountCode::class);
    }

    /**
     * @return HasMany<PromotionUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(PromotionUsage::class);
    }
}
