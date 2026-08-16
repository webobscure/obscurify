<?php

namespace App\Domain\RussianCommerce\Support;

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\RussianCommerce\Enums\FiscalizableType;
use App\Domain\RussianCommerce\Enums\FiscalReceiptItemPaymentSubject;
use App\Domain\RussianCommerce\Enums\VatRate;
use App\Domain\RussianCommerce\Models\FiscalizationSettings;
use App\Domain\RussianCommerce\Models\ProductFiscalProfile;

/**
 * Resolution order for a variant's fiscal attributes (spec section 12):
 * a variant-level ProductFiscalProfile override, else the variant's
 * product-level profile, else the store's FiscalizationSettings default
 * VAT rate + a plain Commodity payment subject. Every FiscalReceiptItem
 * is built through this — no other code reads ProductFiscalProfile
 * directly.
 */
final class ResolveProductFiscalProfile
{
    /**
     * @return array{vat_rate: VatRate, payment_subject: FiscalReceiptItemPaymentSubject, unit_of_measure: string|null}
     */
    public function handle(ProductVariant $variant): array
    {
        $variantProfile = ProductFiscalProfile::query()
            ->where('fiscalizable_type', FiscalizableType::ProductVariant->value)
            ->where('fiscalizable_id', $variant->id)
            ->first();

        if ($variantProfile !== null) {
            return $this->fromProfile($variantProfile);
        }

        $productProfile = ProductFiscalProfile::query()
            ->where('fiscalizable_type', FiscalizableType::Product->value)
            ->where('fiscalizable_id', $variant->product_id)
            ->first();

        if ($productProfile !== null) {
            return $this->fromProfile($productProfile);
        }

        $settings = FiscalizationSettings::query()->where('store_id', $variant->store_id)->first();
        $vatRate = $settings !== null ? $settings->default_vat_rate : VatRate::None;

        return [
            'vat_rate' => $vatRate,
            'payment_subject' => FiscalReceiptItemPaymentSubject::Commodity,
            'unit_of_measure' => null,
        ];
    }

    /**
     * @return array{vat_rate: VatRate, payment_subject: FiscalReceiptItemPaymentSubject, unit_of_measure: string|null}
     */
    private function fromProfile(ProductFiscalProfile $profile): array
    {
        return [
            'vat_rate' => $profile->vat_rate,
            'payment_subject' => $profile->payment_subject,
            'unit_of_measure' => $profile->unit_of_measure,
        ];
    }
}
