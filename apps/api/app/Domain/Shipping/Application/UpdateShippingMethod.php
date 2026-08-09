<?php

namespace App\Domain\Shipping\Application;

use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Models\ShippingMethodZone;
use Illuminate\Support\Facades\DB;

final class UpdateShippingMethod
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(ShippingMethod $method, array $data): ShippingMethod
    {
        $zoneIds = $data['zone_ids'] ?? null;
        unset($data['zone_ids']);

        return DB::transaction(function () use ($method, $data, $zoneIds) {
            $method->update($data);

            // Replace-in-full, same pragmatic choice as
            // UpdateShippingZone's own region handling — see ShippingMethodZone's
            // docblock for why this goes through the pivot model directly.
            if ($zoneIds !== null) {
                ShippingMethodZone::query()->where('shipping_method_id', $method->id)->delete();

                foreach ($zoneIds as $zoneId) {
                    ShippingMethodZone::query()->create([
                        'shipping_method_id' => $method->id,
                        'shipping_zone_id' => $zoneId,
                    ]);
                }
            }

            return $method->load('zones');
        });
    }
}
