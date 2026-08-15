<?php

namespace App\Domain\Search\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A store's own configured instance of a provider `code` — mirrors
 * NotificationProvider exactly. "database" is the only code actually
 * resolvable through SearchProviderRegistry; every other code in
 * FUTURE_CODES is a selectable placeholder (spec: "Future providers")
 * that fails at search time with UnknownSearchProviderException.
 *
 * @property string $id
 * @property string $store_id
 * @property string $code
 * @property string $name
 * @property bool $is_enabled
 * @property array<string, mixed>|null $config
 */
class SearchProvider extends Model
{
    use BelongsToTenant, HasUlids;

    public const string DATABASE = 'database';

    /**
     * @var string[]
     */
    public const array FUTURE_CODES = ['meilisearch', 'typesense', 'opensearch', 'elasticsearch'];

    protected $fillable = [
        'code',
        'name',
        'is_enabled',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'config' => 'array',
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
