<?php

namespace App\Domain\Shipping\Application;

use App\Domain\Shipping\Models\ShippingZone;
use App\Domain\Shipping\Models\ShippingZoneRegion;
use Illuminate\Support\Facades\DB;

final class UpdateShippingZone
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(ShippingZone $zone, array $data): ShippingZone
    {
        $regions = $data['regions'] ?? null;
        unset($data['regions']);

        return DB::transaction(function () use ($zone, $data, $regions) {
            $zone->update($data);

            // Regions are small (a handful per zone) and have no identity
            // a merchant would reference elsewhere — replace-in-full is
            // simpler and safer than diffing, and matches the pragmatic
            // bar this milestone set (spec section 1).
            if ($regions !== null) {
                $zone->regions()->delete();

                foreach ($regions as $region) {
                    ShippingZoneRegion::query()->create([
                        'shipping_zone_id' => $zone->id,
                        'country_code' => strtoupper($region['country_code']),
                        'region' => $region['region'] ?? null,
                        'postal_code_pattern' => $region['postal_code_pattern'] ?? null,
                    ]);
                }
            }

            return $zone->load('regions');
        });
    }
}
