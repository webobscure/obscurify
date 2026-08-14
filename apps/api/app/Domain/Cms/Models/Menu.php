<?php

namespace App\Domain\Cms\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named navigation menu. See the migration's docblock.
 *
 * @property string $id
 * @property string $store_id
 * @property string $name
 * @property string $handle
 */
class Menu extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['name', 'handle'];

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function topLevelItems(): HasMany
    {
        return $this->items()->whereNull('parent_id')->orderBy('position');
    }
}
