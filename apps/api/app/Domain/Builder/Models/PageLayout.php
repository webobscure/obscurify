<?php

namespace App\Domain\Builder\Models;

use App\Domain\Cms\Models\PageVersion;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * The Builder's structured root for one (always draft) PageVersion. See
 * the migration's docblock and docs/architecture/page-builder.md.
 *
 * @property string $id
 * @property string $store_id
 * @property string $page_version_id
 */
class PageLayout extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['page_version_id'];

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<PageVersion, $this>
     */
    public function pageVersion(): BelongsTo
    {
        return $this->belongsTo(PageVersion::class);
    }

    /**
     * @return HasMany<SectionInstance, $this>
     */
    public function sectionInstances(): HasMany
    {
        return $this->hasMany(SectionInstance::class)->orderBy('position');
    }

    /**
     * @return HasMany<BuilderRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(BuilderRevision::class);
    }

    /**
     * @return HasOne<BuilderHistory, $this>
     */
    public function history(): HasOne
    {
        return $this->hasOne(BuilderHistory::class);
    }
}
