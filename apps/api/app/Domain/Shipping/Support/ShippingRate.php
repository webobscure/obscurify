<?php

namespace App\Domain\Shipping\Support;

/**
 * A calculated, not-yet-selected shipping option (spec section 5).
 * Ephemeral by default — see the class docblock on ShippingQuote for when
 * this actually gets persisted. `methodId` is nullable because a future
 * provider could quote a service with no corresponding ShippingMethod row
 * (e.g. a live rate lookup); every method this milestone's FakeShipping-
 * Provider quotes does have one.
 *
 * @param  array<string, mixed>  $metadata
 */
final readonly class ShippingRate
{
    public function __construct(
        public string $provider,
        public ?string $serviceCode,
        public ?string $methodId,
        public string $name,
        public int $priceAmount,
        public string $currency,
        public ?int $estimatedDaysMin,
        public ?int $estimatedDaysMax,
        public array $metadata = [],
    ) {}
}
