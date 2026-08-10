<?php

namespace App\Domain\Promotions\Models;

use App\Domain\Promotions\Enums\PromotionActionType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $store_id
 * @property string $promotion_id
 * @property PromotionActionType $type
 * @property array<string, mixed> $parameters
 */
class PromotionAction extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'promotion_id',
        'type',
        'parameters',
    ];

    protected function casts(): array
    {
        return [
            'type' => PromotionActionType::class,
            'parameters' => 'array',
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
     * @return BelongsTo<Promotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
