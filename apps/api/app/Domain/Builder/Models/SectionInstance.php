<?php

namespace App\Domain\Builder\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One placed section on a PageLayout. See the migration's docblock.
 *
 * @property string $id
 * @property string $store_id
 * @property string $page_layout_id
 * @property string $section_handle
 * @property int $position
 * @property array<string, mixed> $settings
 */
class SectionInstance extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['page_layout_id', 'section_handle', 'position', 'settings'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'settings' => 'array',
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
     * @return BelongsTo<PageLayout, $this>
     */
    public function pageLayout(): BelongsTo
    {
        return $this->belongsTo(PageLayout::class);
    }

    /**
     * @return HasMany<BlockInstance, $this>
     */
    public function topLevelBlocks(): HasMany
    {
        return $this->hasMany(BlockInstance::class)->whereNull('parent_block_instance_id')->orderBy('position');
    }

    /**
     * @return HasMany<BlockInstance, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(BlockInstance::class);
    }
}
