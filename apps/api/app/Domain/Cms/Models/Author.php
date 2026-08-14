<?php

namespace App\Domain\Cms\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A blog post byline. See the migration's docblock for why this is not
 * the staff User model.
 *
 * @property string $id
 * @property string $store_id
 * @property string $name
 * @property string|null $bio
 * @property string|null $avatar_path
 */
class Author extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['name', 'bio', 'avatar_path'];

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
