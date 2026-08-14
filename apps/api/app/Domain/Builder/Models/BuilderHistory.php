<?php

namespace App\Domain\Builder\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The undo/redo cursor for one PageLayout. See the migration's docblock.
 *
 * @property string $id
 * @property string $store_id
 * @property string $page_layout_id
 * @property string|null $current_revision_id
 */
class BuilderHistory extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['page_layout_id', 'current_revision_id'];

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
     * @return BelongsTo<BuilderRevision, $this>
     */
    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(BuilderRevision::class, 'current_revision_id');
    }
}
