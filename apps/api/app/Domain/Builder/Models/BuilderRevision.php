<?php

namespace App\Domain\Builder\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable snapshot of a PageLayout's `sections` array at one point
 * in time. See the migration's docblock.
 *
 * @property string $id
 * @property string $store_id
 * @property string $page_layout_id
 * @property int $sequence
 * @property array<int, array<string, mixed>> $sections
 */
class BuilderRevision extends Model
{
    use BelongsToTenant, HasUlids;

    public $timestamps = false;

    protected $fillable = ['page_layout_id', 'sequence', 'sections', 'created_at'];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'sections' => 'array',
            'created_at' => 'datetime',
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
}
