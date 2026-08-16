<?php

namespace App\Domain\Storefront\Http\Resources;

use App\Domain\RussianCommerce\Models\PaymentMethodSettings;
use App\Domain\RussianCommerce\Models\StoreLegalProfile;
use App\Domain\Stores\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Store
 */
final class StorefrontStoreResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'default_currency' => $this->default_currency,
            'default_locale' => $this->default_locale,
            'timezone' => $this->timezone,
            'seller' => $this->seller(),
            // Every displayed price already includes VAT (spec section
            // 4/18: "Russian receipts always state the customer-facing
            // price as already including VAT") — there is no separate
            // ex-VAT price anywhere in this platform's pricing model, so
            // no additional field is needed to satisfy "VAT-inclusive
            // pricing"; it's the only pricing model that exists.
            'payment_methods' => $this->paymentMethods(),
        ];
    }

    /**
     * Russian Commerce Foundation (spec section 18) — only what a
     * customer needs to identify who they're buying from (legal name,
     * INN — required on a Russian receipt/checkout by consumer-protection
     * norms). Never the full legal profile: no KPP, no addresses, no
     * entity type, no contact details.
     *
     * @return array{legal_name: string, inn: string}|null
     */
    private function seller(): ?array
    {
        $profile = StoreLegalProfile::query()->where('store_id', $this->id)->first();

        if ($profile === null) {
            return null;
        }

        return [
            'legal_name' => $profile->legal_name,
            'inn' => $profile->inn,
        ];
    }

    /**
     * @return list<string>
     */
    private function paymentMethods(): array
    {
        $settings = PaymentMethodSettings::query()->where('store_id', $this->id)->first();

        return $settings !== null ? $settings->enabled_methods : [];
    }
}
