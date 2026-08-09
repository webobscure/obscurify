<?php

namespace App\Domain\Shipping\Application;

use App\Domain\Shipping\Enums\ShippingMethodStatus;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Models\ShippingZone;
use Illuminate\Support\Collection;

/**
 * Available methods for a resolved zone (spec section 4): active methods
 * attached to that zone, active zone already guaranteed by
 * ResolveShippingZone only ever returning active zones. Frontend-supplied
 * method ids are never trusted here or anywhere downstream — this is the
 * one place "available" is decided.
 */
final class ResolveAvailableShippingMethods
{
    /**
     * @return Collection<int, ShippingMethod>
     */
    public function handle(ShippingZone $zone): Collection
    {
        return $zone->shippingMethods()
            ->where('status', ShippingMethodStatus::Active)
            ->get();
    }
}
