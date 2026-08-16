<?php

namespace App\Domain\RussianCommerce\Models;

use App\Domain\RussianCommerce\Enums\VatRate;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per store — active fiscalization provider + default VAT rate
 * (spec section 17's "Tax / VAT Settings" page) + whether a receipt is
 * required before checkout completion can proceed.
 *
 * @property string $id
 * @property string $store_id
 * @property string|null $active_provider_id
 * @property VatRate $default_vat_rate
 * @property bool $receipts_required
 */
class FiscalizationSettings extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'active_provider_id',
        'default_vat_rate',
        'receipts_required',
    ];

    protected function casts(): array
    {
        return [
            'default_vat_rate' => VatRate::class,
            'receipts_required' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<FiscalizationProvider, $this>
     */
    public function activeProvider(): BelongsTo
    {
        return $this->belongsTo(FiscalizationProvider::class, 'active_provider_id');
    }
}
