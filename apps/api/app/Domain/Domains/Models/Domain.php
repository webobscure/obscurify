<?php

namespace App\Domain\Domains\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Foundation model only: no DNS verification, SSL provisioning, or
 * wildcard automation. Exists so future storefront tenant resolution
 * (Host header -> Domain -> Store -> TenantContext) has a stable table
 * to build on.
 *
 * @property string $id
 * @property string $store_id
 * @property string $domain
 * @property string $type
 * @property bool $is_primary
 * @property Carbon|null $verified_at
 */
class Domain extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'domain',
        'type',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
