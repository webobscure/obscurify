<?php

namespace App\Domain\Shipping\Application;

use App\Domain\Carts\Models\CartItem;
use App\Domain\Checkouts\Enums\CheckoutStatus;
use App\Domain\Checkouts\Models\Checkout;
use App\Domain\Checkouts\Models\CheckoutAddress;
use App\Domain\Shipping\Exceptions\PickupPointInvalidException;
use App\Domain\Shipping\Exceptions\ShippingQuoteInvalidException;
use App\Domain\Shipping\Models\ShippingQuote;
use App\Domain\Shipping\Support\ShipmentWeightCalculator;
use App\Domain\Shipping\Support\ShippingProviderRegistry;
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
 * price"). Weight is likewise always recomputed from the checkout's own
 * cart, never trusted (spec section 3) — see ShipmentWeightCalculator.
 */
final class SelectShippingRate
{
    public function __construct(
        private readonly CalculateShippingRates $calculateShippingRates,
        private readonly ShipmentWeightCalculator $weightCalculator,
        private readonly ShippingProviderRegistry $registry,
    ) {}

    public function handle(Checkout $checkout, string $provider, ?string $serviceCode, ?string $methodId, ?string $pickupPointId = null): Checkout
    {
        return DB::transaction(function () use ($checkout, $provider, $serviceCode, $methodId, $pickupPointId) {
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

            $cart = $locked->cart()->firstOrFail();
            $lines = $cart->items()->with('variant')->get()
                ->map(fn (CartItem $item) => ['variant' => $item->variant, 'quantity' => $item->quantity]);

            $context = new ShippingRateContext(
                countryCode: $shippingAddress->country_code ?? '',
                region: $shippingAddress->region,
                postalCode: $shippingAddress->postal_code,
                currency: $locked->currency,
                weightKg: $this->weightCalculator->handle($lines)->billableKg,
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

            // Pickup point selection (spec section 6): required exactly
            // when the selected rate is a pickup service, and must be one
            // of the points a *fresh* provider lookup for this exact
            // context actually returned — never trusted from the client
            // beyond its id, and never a point some other service/context
            // merely claims to recognize.
            $pickupPointSnapshot = null;

            if ($selected->serviceCode === 'pickup') {
                if ($pickupPointId === null) {
                    throw PickupPointInvalidException::make('a pickup point is required for this service.');
                }

                $availablePoints = $this->registry->resolve($provider)->listPickupPoints($context);
                $matchedPoint = $availablePoints->first(fn ($point) => $point->id === $pickupPointId);

                if ($matchedPoint === null) {
                    throw PickupPointInvalidException::make('the selected pickup point is not available for this provider/destination.');
                }

                $pickupPointSnapshot = [
                    'id' => $matchedPoint->id,
                    'name' => $matchedPoint->name,
                    'address' => $matchedPoint->address,
                    'city' => $matchedPoint->city,
                    'postal_code' => $matchedPoint->postalCode,
                    'opening_hours' => $matchedPoint->openingHours,
                    'latitude' => $matchedPoint->latitude,
                    'longitude' => $matchedPoint->longitude,
                ];
            } elseif ($pickupPointId !== null) {
                throw PickupPointInvalidException::make('a pickup point is only valid for the pickup service.');
            }

            $expiresAt = now()->addMinutes((int) config('commerce.shipping.quote_ttl_minutes'));

            // metadata carries the rate's own provider metadata plus the
            // pickup point snapshot, if any — both persisted here so the
            // Order snapshot (CompleteCheckout copies this verbatim into
            // OrderShippingLine.metadata) never depends on a later
            // provider/config lookup (spec section 18).
            $metadata = $selected->metadata;
            if ($pickupPointSnapshot !== null) {
                $metadata['pickup_point'] = $pickupPointSnapshot;
            }

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
                'metadata' => $metadata === [] ? null : $metadata,
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
