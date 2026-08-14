<?php

namespace App\Domain\Builder\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One placed block within a SectionInstance, optionally nested inside
 * another block. See the migration's docblock.
 *
 * @property string $id
 * @property string $store_id
 * @property string $section_instance_id
 * @property string|null $parent_block_instance_id
 * @property string $block_handle
 * @property int $position
 * @property array<string, mixed> $settings
 */
class BlockInstance extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['section_instance_id', 'parent_block_instance_id', 'block_handle', 'position', 'settings'];

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
     * @return BelongsTo<SectionInstance, $this>
     */
    public function sectionInstance(): BelongsTo
    {
        return $this->belongsTo(SectionInstance::class);
    }

    /**
     * @return BelongsTo<BlockInstance, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(BlockInstance::class, 'parent_block_instance_id');
    }

    /**
     * @return HasMany<BlockInstance, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(BlockInstance::class, 'parent_block_instance_id')->orderBy('position');
    }
}
