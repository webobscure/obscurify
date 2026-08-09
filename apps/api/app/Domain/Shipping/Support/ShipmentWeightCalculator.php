<?php

namespace App\Domain\Shipping\Support;

use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * Weight/dimensions come exclusively from ProductVariant rows, never from
 * request input (spec section 3: "do not trust weight supplied by
 * frontend") — every caller (CalculateShippingRates via the storefront
 * controller, SelectShippingRate) rebuilds this from the checkout's own
 * cart items each time, the same "never cache/trust" discipline the
 * destination address already follows.
 *
 * Policy for a variant with no weight (spec section 3 requires one to be
 * chosen and documented): treated as 0kg, not an error and not a guess —
 * the shipment is still quotable, it just doesn't contribute weight-based
 * cost for that line. A merchant who wants accurate weight-based pricing
 * fills in the variant's weight; catalog data completeness is not this
 * calculator's concern to enforce.
 */
final class ShipmentWeightCalculator
{
    /**
     * @param  Collection<int, array{variant: ProductVariant, quantity: int}>  $lines
     */
    public function handle(Collection $lines): ShipmentWeight
    {
        $divisor = max(1, (int) config('commerce.shipping.fake.volumetric_divisor'));

        $actualKg = 0.0;
        $volumetricKg = 0.0;

        foreach ($lines as $line) {
            $variant = $line['variant'];
            $quantity = $line['quantity'];

            $weightKg = $variant->weight !== null ? (float) $variant->weight : 0.0;
            $actualKg += $weightKg * $quantity;

            if ($variant->length !== null && $variant->width !== null && $variant->height !== null) {
                $cubicCentimeters = (float) $variant->length * (float) $variant->width * (float) $variant->height;
                $volumetricKg += ($cubicCentimeters / $divisor) * $quantity;
            }
        }

        return new ShipmentWeight(
            actualKg: $actualKg,
            volumetricKg: $volumetricKg,
            billableKg: max($actualKg, $volumetricKg),
        );
    }
}
