<?php

namespace App\Domain\Shipping\Application;

use App\Domain\Shipping\Enums\ShippingZoneStatus;
use App\Domain\Shipping\Models\ShippingZone;
use App\Domain\Shipping\Models\ShippingZoneRegion;
use Illuminate\Support\Facades\DB;

final class CreateShippingZone
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): ShippingZone
    {
        $regions = $data['regions'] ?? [];
        unset($data['regions']);

        $data['status'] ??= ShippingZoneStatus::Active->value;

        return DB::transaction(function () use ($data, $regions) {
            $zone = ShippingZone::query()->create($data);

            foreach ($regions as $region) {
                ShippingZoneRegion::query()->create([
                    'shipping_zone_id' => $zone->id,
                    'country_code' => strtoupper($region['country_code']),
                    'region' => $region['region'] ?? null,
                    'postal_code_pattern' => $region['postal_code_pattern'] ?? null,
                ]);
            }

            return $zone->load('regions');
        });
    }
}
