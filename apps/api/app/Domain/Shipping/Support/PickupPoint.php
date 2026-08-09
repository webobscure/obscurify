<?php

namespace App\Domain\Shipping\Support;

/**
 * Provider-neutral pickup-point DTO (spec section 5) — a real carrier
 * would return its own network the same shape; nothing outside the
 * Shipping module ever sees a provider-specific point representation.
 */
final readonly class PickupPoint
{
    public function __construct(
        public string $id,
        public string $name,
        public string $address,
        public string $city,
        public string $countryCode,
        public ?string $postalCode,
        public ?string $openingHours,
        public ?float $latitude,
        public ?float $longitude,
    ) {}
}
