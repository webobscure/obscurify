<?php

namespace App\Domain\Cms\Models;

use App\Domain\Cms\Enums\PageStatus;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A CMS page "product" a store owns — the actual editable content lives
 * on its PageVersion rows. See docs/architecture/cms.md.
 *
 * @property string $id
 * @property string $store_id
 * @property string $title
 * @property string $slug
 * @property PageStatus $status
 */
class Page extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['title', 'slug', 'status'];

    protected function casts(): array
    {
        return ['status' => PageStatus::class];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<PageVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(PageVersion::class);
    }

    /**
     * @return HasOne<ActivePageVersion, $this>
     */
    public function activePointer(): HasOne
    {
        return $this->hasOne(ActivePageVersion::class, 'page_id');
    }
}
