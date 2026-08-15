<?php

namespace App\Domain\CustomerIntelligence\Models;

use App\Domain\CustomerIntelligence\Enums\CustomerGroupStatus;
use App\Domain\CustomerIntelligence\Enums\CustomerGroupType;
use App\Domain\CustomerIntelligence\Enums\SegmentableType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\CustomerGroupFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $store_id
 * @property string $name
 * @property string|null $description
 * @property CustomerGroupType $type
 * @property CustomerGroupStatus $status
 */
class CustomerGroup extends Model
{
    /** @use HasFactory<CustomerGroupFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): CustomerGroupFactory
    {
        return CustomerGroupFactory::new();
    }

    protected $fillable = [
        'name',
        'description',
        'type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomerGroupType::class,
            'status' => CustomerGroupStatus::class,
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
     * @return HasMany<CustomerGroupMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(CustomerGroupMember::class);
    }

    /**
     * Only meaningful when type is Dynamic or Protected — top-level nodes
     * of this group's own rule tree (see SegmentRuleEngine).
     *
     * @return HasMany<SegmentRule, $this>
     */
    public function rootRules(): HasMany
    {
        return $this->hasMany(SegmentRule::class, 'segmentable_id')
            ->where('segmentable_type', SegmentableType::CustomerGroup->value)
            ->whereNull('parent_id')
            ->orderBy('position');
    }

    public function isDynamicallyComputed(): bool
    {
        return $this->type !== CustomerGroupType::Manual;
    }

    /**
     * The cached membership rows AddCustomerToGroup/
     * RefreshCustomerSegmentMemberships maintain — unlike `members()`
     * (manual-only, the actual write target), this covers manual and
     * dynamic/protected groups alike, so it's what `member_count`
     * reports regardless of type.
     *
     * @return HasMany<CustomerSegmentMembership, $this>
     */
    public function computedMemberships(): HasMany
    {
        return $this->hasMany(CustomerSegmentMembership::class, 'segmentable_id')
            ->where('segmentable_type', SegmentableType::CustomerGroup->value);
    }
}
