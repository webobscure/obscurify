<?php

namespace App\Domain\Shipping\Support;

/**
 * What a provider hands back from createShipment(): enough to track the
 * shipment and show the merchant/customer where it is. Nothing
 * provider-specific leaks past this shape into CreateShipment.
 */
final readonly class ShipmentCreationResult
{
    public function __construct(
        public string $externalShipmentId,
        public ?string $trackingNumber,
        public ?string $trackingUrl,
    ) {}
}
