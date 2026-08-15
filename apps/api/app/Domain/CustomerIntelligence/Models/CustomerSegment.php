<?php

namespace App\Domain\CustomerIntelligence\Models;

use App\Domain\CustomerIntelligence\Enums\CustomerSegmentStatus;
use App\Domain\CustomerIntelligence\Enums\SegmentableType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\CustomerSegmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Always dynamic (rule-computed) — see the migration's docblock for why
 * this is distinct from a dynamic CustomerGroup.
 *
 * @property string $id
 * @property string $store_id
 * @property string $name
 * @property string|null $description
 * @property CustomerSegmentStatus $status
 */
class CustomerSegment extends Model
{
    /** @use HasFactory<CustomerSegmentFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): CustomerSegmentFactory
    {
        return CustomerSegmentFactory::new();
    }

    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => CustomerSegmentStatus::class,
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
     * Top-level nodes of this segment's rule tree (see SegmentRuleEngine).
     *
     * @return HasMany<SegmentRule, $this>
     */
    public function rootRules(): HasMany
    {
        return $this->hasMany(SegmentRule::class, 'segmentable_id')
            ->where('segmentable_type', SegmentableType::CustomerSegment->value)
            ->whereNull('parent_id')
            ->orderBy('position');
    }

    /**
     * The cached membership rows RefreshCustomerSegmentMemberships
     * maintains — exists mainly so `withCount('computedMemberships')`
     * can report a member count without re-evaluating the rule tree.
     *
     * @return HasMany<CustomerSegmentMembership, $this>
     */
    public function computedMemberships(): HasMany
    {
        return $this->hasMany(CustomerSegmentMembership::class, 'segmentable_id')
            ->where('segmentable_type', SegmentableType::CustomerSegment->value);
    }
}
