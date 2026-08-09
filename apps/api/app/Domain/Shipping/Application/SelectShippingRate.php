<?php

namespace App\Domain\Shipping\Application;

use App\Domain\Checkouts\Enums\CheckoutStatus;
use App\Domain\Checkouts\Models\Checkout;
use App\Domain\Checkouts\Models\CheckoutAddress;
use App\Domain\Shipping\Exceptions\ShippingQuoteInvalidException;
use App\Domain\Shipping\Models\ShippingQuote;
use App\Domain\Shipping\Support\ShippingRateContext;
use App\Shared\Commerce\Enums\AddressType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Selects one shipping rate for a checkout (spec section 10). The price is
 * never trusted from the client — (provider, service_code, method_id) only
 * identifies *which* rate was picked; the actual price/name/estimate are
 * always re-read from a fresh CalculateShippingRates call and persisted
 * from there (spec section 11: "do not trust frontend-submitted shipping
 * price").
 */
final class SelectShippingRate
{
    public function __construct(
        private readonly CalculateShippingRates $calculateShippingRates,
    ) {}

    public function handle(Checkout $checkout, string $provider, ?string $serviceCode, ?string $methodId): Checkout
    {
        return DB::transaction(function () use ($checkout, $provider, $serviceCode, $methodId) {
            $locked = Checkout::query()->whereKey($checkout->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== CheckoutStatus::Open) {
                throw ValidationException::withMessages([
                    'checkout' => 'This checkout is not open.',
                ]);
            }

            $shippingAddress = CheckoutAddress::query()
                ->where('checkout_id', $locked->id)
                ->where('type', AddressType::Shipping->value)
                ->first();

            if ($shippingAddress === null) {
                throw ValidationException::withMessages([
                    'shipping_address' => 'A shipping address is required before selecting a shipping method.',
                ]);
            }

            $context = new ShippingRateContext(
                countryCode: $shippingAddress->country_code ?? '',
                region: $shippingAddress->region,
                postalCode: $shippingAddress->postal_code,
                currency: $locked->currency,
            );

            $rates = $this->calculateShippingRates->handle($context);

            $selected = $rates->first(
                fn ($rate) => $rate->provider === $provider
                    && $rate->serviceCode === $serviceCode
                    && $rate->methodId === $methodId
            );

            if ($selected === null) {
                throw ShippingQuoteInvalidException::make('the selected rate is no longer available.');
            }

            $expiresAt = now()->addMinutes((int) config('commerce.shipping.quote_ttl_minutes'));

            $quote = ShippingQuote::query()->create([
                'checkout_id' => $locked->id,
                'shipping_method_id' => $selected->methodId,
                'provider' => $selected->provider,
                'service_code' => $selected->serviceCode,
                'name' => $selected->name,
                'price_amount' => $selected->priceAmount,
                'currency' => $selected->currency,
                'estimated_days_min' => $selected->estimatedDaysMin,
                'estimated_days_max' => $selected->estimatedDaysMax,
                'expires_at' => $expiresAt,
                'metadata' => $selected->metadata,
            ]);

            $totalAmount = $locked->items_subtotal_amount + $selected->priceAmount
                - $locked->discount_amount + $locked->tax_amount;

            $locked->update([
                'shipping_quote_id' => $quote->id,
                'shipping_amount' => $selected->priceAmount,
                'total_amount' => $totalAmount,
            ]);

            return $locked->fresh(['addresses']);
        });
    }
}
