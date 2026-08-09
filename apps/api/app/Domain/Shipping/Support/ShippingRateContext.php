<?php

namespace App\Domain\Shipping\Support;

/**
 * Everything a provider needs to price a shipment — deliberately just the
 * destination and currency this milestone, not weight/dimensions: every
 * ShippingMethod's price is currently flat (spec section 2), so nothing
 * yet reads a cart's physical characteristics. Extend here, not by adding
 * parameters to calculateRates() itself, when that changes.
 */
final readonly class ShippingRateContext
{
    public function __construct(
        public string $countryCode,
        public ?string $region,
        public ?string $postalCode,
        public string $currency,
    ) {}
}
