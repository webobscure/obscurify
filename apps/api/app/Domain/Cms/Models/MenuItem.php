<?php

namespace App\Domain\Cms\Models;

use App\Domain\Cms\Enums\MenuItemTargetType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One entry in a Menu, self-nesting via `parent_id`. See the migration's
 * docblock for the target_type/target_id/url pattern.
 *
 * @property string $id
 * @property string $store_id
 * @property string $menu_id
 * @property string|null $parent_id
 * @property string $label
 * @property MenuItemTargetType $target_type
 * @property string|null $target_id
 * @property string|null $url
 * @property int $position
 */
class MenuItem extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['menu_id', 'parent_id', 'label', 'target_type', 'target_id', 'url', 'position'];

    protected function casts(): array
    {
        return [
            'target_type' => MenuItemTargetType::class,
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
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('position');
    }
}
