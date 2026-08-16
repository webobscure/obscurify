<?php

namespace App\Domain\RussianCommerce\Models;

use App\Domain\Orders\Models\Order;
use App\Domain\RussianCommerce\Enums\LegalEntityType;
use App\Domain\RussianCommerce\Enums\VatRate;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Spec section 11 — written once at order completion (BuildOrderFiscalSnapshot),
 * never updated afterward. See the migration's own docblock for why this
 * is a real snapshot, not a live read of StoreLegalProfile.
 *
 * @property string $id
 * @property string $store_id
 * @property string $order_id
 * @property LegalEntityType $seller_legal_entity_type
 * @property string $seller_legal_name
 * @property string $seller_inn
 * @property string|null $seller_kpp
 * @property VatRate $vat_rate
 * @property int $vat_amount
 * @property bool $receipt_required
 */
class OrderFiscalSnapshot extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'order_id',
        'seller_legal_entity_type',
        'seller_legal_name',
        'seller_inn',
        'seller_kpp',
        'vat_rate',
        'vat_amount',
        'receipt_required',
    ];

    protected function casts(): array
    {
        return [
            'seller_legal_entity_type' => LegalEntityType::class,
            'vat_rate' => VatRate::class,
            'vat_amount' => 'integer',
            'receipt_required' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
