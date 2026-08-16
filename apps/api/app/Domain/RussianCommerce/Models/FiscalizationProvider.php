<?php

namespace App\Domain\RussianCommerce\Models;

use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Mirrors SearchProvider/NotificationProvider exactly. `FUTURE_CODES`
 * lists the real OFD/fiscal integrations spec section 22 explicitly
 * excludes this milestone — selectable in the admin UI as catalog
 * placeholders, none registered in FiscalizationProviderRegistry.
 * `credentials` is always-encrypted (spec section 20) and separate from
 * `config` (plain jsonb) — see casts().
 *
 * @property string $id
 * @property string $store_id
 * @property string $code
 * @property string $name
 * @property bool $is_enabled
 * @property array<string, mixed>|null $config
 * @property string|null $credentials
 */
class FiscalizationProvider extends Model
{
    use BelongsToTenant, HasUlids;

    public const string FAKE = 'fake';

    public const array FUTURE_CODES = ['atol', 'orange_data', 'cloud_kassir'];

    protected $fillable = [
        'code',
        'name',
        'is_enabled',
        'config',
        'credentials',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'config' => 'array',
            'credentials' => 'encrypted',
        ];
    }
}
