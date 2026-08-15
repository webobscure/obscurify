<?php

namespace App\Domain\CustomerIntelligence\Models;

use App\Domain\CustomerIntelligence\Enums\SegmentRuleBoolean;
use App\Domain\CustomerIntelligence\Enums\SegmentRuleField;
use App\Domain\CustomerIntelligence\Enums\SegmentRuleOperator;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One node in a rule tree — either a *group* node (`boolean_operator`
 * set, has `children`) or a *condition* node (`field`/`operator`/`value`
 * set, no children). See the migration's docblock for the full shape.
 *
 * @property string $id
 * @property string $store_id
 * @property string $segmentable_type
 * @property string $segmentable_id
 * @property string|null $parent_id
 * @property SegmentRuleBoolean|null $boolean_operator
 * @property SegmentRuleField|null $field
 * @property SegmentRuleOperator|null $operator
 * @property mixed $value
 * @property int $position
 */
class SegmentRule extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'segmentable_type',
        'segmentable_id',
        'parent_id',
        'boolean_operator',
        'field',
        'operator',
        'value',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'boolean_operator' => SegmentRuleBoolean::class,
            'field' => SegmentRuleField::class,
            'operator' => SegmentRuleOperator::class,
            'value' => 'array',
            'position' => 'integer',
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
     * @return BelongsTo<SegmentRule, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(SegmentRule::class, 'parent_id');
    }

    /**
     * @return HasMany<SegmentRule, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(SegmentRule::class, 'parent_id')->orderBy('position');
    }

    public function isGroup(): bool
    {
        return $this->boolean_operator !== null;
    }
}
