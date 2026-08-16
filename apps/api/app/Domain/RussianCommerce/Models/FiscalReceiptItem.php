<?php

namespace App\Domain\RussianCommerce\Models;

use App\Domain\RussianCommerce\Enums\FiscalReceiptItemPaymentMethod;
use App\Domain\RussianCommerce\Enums\FiscalReceiptItemPaymentSubject;
use App\Domain\RussianCommerce\Enums\VatRate;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Spec section 6 — one 54-FZ line item. `product_id`/`product_variant_id`
 * are informational-only back-references (no FK constraint) — see the
 * migration's own docblock for why.
 *
 * @property string $id
 * @property string $store_id
 * @property string $fiscal_receipt_id
 * @property string|null $product_id
 * @property string|null $product_variant_id
 * @property string $name
 * @property float $quantity
 * @property int $price_amount
 * @property int $amount
 * @property VatRate $vat_rate
 * @property FiscalReceiptItemPaymentMethod $payment_method
 * @property FiscalReceiptItemPaymentSubject $payment_subject
 * @property string|null $unit_of_measure
 */
class FiscalReceiptItem extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'fiscal_receipt_id',
        'product_id',
        'product_variant_id',
        'name',
        'quantity',
        'price_amount',
        'amount',
        'vat_rate',
        'payment_method',
        'payment_subject',
        'unit_of_measure',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'price_amount' => 'integer',
            'amount' => 'integer',
            'vat_rate' => VatRate::class,
            'payment_method' => FiscalReceiptItemPaymentMethod::class,
            'payment_subject' => FiscalReceiptItemPaymentSubject::class,
        ];
    }

    /**
     * @return BelongsTo<FiscalReceipt, $this>
     */
    public function fiscalReceipt(): BelongsTo
    {
        return $this->belongsTo(FiscalReceipt::class);
    }
}
