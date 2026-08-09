<?php

namespace App\Domain\Shipping\Support;

/**
 * A shipment's weight, computed once server-side and never trusted from
 * the client (spec section 3). `billableKg` is what a rate calculation
 * should actually charge for — the greater of actual and volumetric
 * ("dimensional") weight, the same rule real carriers use to charge for
 * bulky-but-light packages.
 */
final readonly class ShipmentWeight
{
    public function __construct(
        public float $actualKg,
        public float $volumetricKg,
        public float $billableKg,
    ) {}
}
