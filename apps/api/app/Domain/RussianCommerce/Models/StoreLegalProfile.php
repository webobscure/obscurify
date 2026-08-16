<?php

namespace App\Domain\RussianCommerce\Models;

use App\Domain\RussianCommerce\Enums\LegalEntityType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Spec section 1: a store's Russian legal identity — kept deliberately
 * separate from Store itself (name/slug/currency/locale, the
 * storefront-facing row), not columns bolted onto it. Every completed
 * Order snapshots the relevant fields into OrderFiscalSnapshot at
 * completion time, so editing this row never rewrites history.
 *
 * @property string $id
 * @property string $store_id
 * @property LegalEntityType $legal_entity_type
 * @property string $legal_name
 * @property string|null $short_name
 * @property string $inn
 * @property string|null $kpp
 * @property string|null $ogrn
 * @property string|null $ogrnip
 * @property array<string, mixed>|null $legal_address
 * @property array<string, mixed>|null $actual_address
 * @property string|null $email
 * @property string|null $phone
 */
class StoreLegalProfile extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'legal_entity_type',
        'legal_name',
        'short_name',
        'inn',
        'kpp',
        'ogrn',
        'ogrnip',
        'legal_address',
        'actual_address',
        'email',
        'phone',
    ];

    protected function casts(): array
    {
        return [
            'legal_entity_type' => LegalEntityType::class,
            'legal_address' => 'array',
            'actual_address' => 'array',
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
