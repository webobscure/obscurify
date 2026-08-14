<?php

namespace App\Domain\Cms\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Points a page at a specific *published* version — mirrors ActiveTheme,
 * scoped per-page instead of per-store. See docs/architecture/cms.md.
 *
 * @property string $id
 * @property string $store_id
 * @property string $page_id
 * @property string $page_version_id
 * @property Carbon $activated_at
 */
class ActivePageVersion extends Model
{
    use BelongsToTenant, HasUlids;

    public $timestamps = false;

    protected $fillable = ['page_id', 'page_version_id', 'activated_at'];

    protected function casts(): array
    {
        return ['activated_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return BelongsTo<PageVersion, $this>
     */
    public function pageVersion(): BelongsTo
    {
        return $this->belongsTo(PageVersion::class);
    }
}
