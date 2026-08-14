<?php

namespace App\Domain\Cms\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reusable starting layout for a new page. See the migration's
 * docblock for why this is a plain preset, not a live reference.
 *
 * @property string $id
 * @property string $store_id
 * @property string $name
 * @property array<int, array<string, mixed>> $sections
 */
class PageTemplate extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['name', 'sections'];

    protected function casts(): array
    {
        return ['sections' => 'array'];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
