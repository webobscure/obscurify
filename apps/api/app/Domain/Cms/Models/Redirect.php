<?php

namespace App\Domain\Cms\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manual URL redirect. See the migration's docblock.
 *
 * @property string $id
 * @property string $store_id
 * @property string $from_path
 * @property string $to_path
 * @property int $status_code
 */
class Redirect extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['from_path', 'to_path', 'status_code'];

    protected function casts(): array
    {
        return ['status_code' => 'integer'];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
