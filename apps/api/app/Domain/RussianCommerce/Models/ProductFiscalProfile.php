<?php

namespace App\Domain\RussianCommerce\Models;

use App\Domain\RussianCommerce\Enums\FiscalizableType;
use App\Domain\RussianCommerce\Enums\FiscalReceiptItemPaymentSubject;
use App\Domain\RussianCommerce\Enums\VatRate;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Spec section 12 — VAT rate, payment subject, and unit of measure for
 * a Product or ProductVariant, kept off `products`/`product_variants`
 * (see the migration's own docblock). One row per (fiscalizable_type,
 * fiscalizable_id); resolution order (product-level, variant-level
 * override) lives in ResolveProductFiscalProfile.
 *
 * @property string $id
 * @property string $store_id
 * @property FiscalizableType $fiscalizable_type
 * @property string $fiscalizable_id
 * @property VatRate $vat_rate
 * @property FiscalReceiptItemPaymentSubject $payment_subject
 * @property string|null $unit_of_measure
 */
class ProductFiscalProfile extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'fiscalizable_type',
        'fiscalizable_id',
        'vat_rate',
        'payment_subject',
        'unit_of_measure',
    ];

    protected function casts(): array
    {
        return [
            'fiscalizable_type' => FiscalizableType::class,
            'vat_rate' => VatRate::class,
            'payment_subject' => FiscalReceiptItemPaymentSubject::class,
        ];
    }
}
