<?php

namespace App\Domain\Cms\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named collection of posts. See the migration's docblock.
 *
 * @property string $id
 * @property string $store_id
 * @property string $title
 * @property string $slug
 */
class Blog extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['title', 'slug'];

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<BlogPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
    }
}
