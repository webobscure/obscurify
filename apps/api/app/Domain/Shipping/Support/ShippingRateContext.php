<?php

namespace App\Domain\Shipping\Support;

/**
 * Everything a provider needs to price a shipment. `weightKg` is the
 * checkout's billable weight (see ShipmentWeightCalculator) — computed
 * server-side from the cart's own ProductVariant rows by every caller,
 * never accepted from a client. Extended here (Reference Provider
 * Hardening), not by adding parameters to calculateRates() itself, per
 * this class's own original guidance.
 */
final readonly class ShippingRateContext
{
    public function __construct(
        public string $countryCode,
        public ?string $region,
        public ?string $postalCode,
        public string $currency,
        public float $weightKg = 0.0,
    ) {}
}
