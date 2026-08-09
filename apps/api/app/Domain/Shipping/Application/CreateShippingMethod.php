<?php

namespace App\Domain\Shipping\Application;

use App\Domain\Shipping\Enums\ShippingMethodStatus;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Models\ShippingMethodZone;
use Illuminate\Support\Facades\DB;

final class CreateShippingMethod
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): ShippingMethod
    {
        $zoneIds = $data['zone_ids'] ?? [];
        unset($data['zone_ids']);

        $data['status'] ??= ShippingMethodStatus::Active->value;

        return DB::transaction(function () use ($data, $zoneIds) {
            $method = ShippingMethod::query()->create($data);

            // Tenant-scoped request validation already confirmed every
            // zone id belongs to the active store (see
            // StoreShippingMethodRequest) — created via the pivot model
            // directly, not Eloquent's sync()/attach(), so
            // BelongsToTenant's creating() hook fills store_id (see
            // ShippingMethodZone's docblock).
            foreach ($zoneIds as $zoneId) {
                ShippingMethodZone::query()->firstOrCreate([
                    'shipping_method_id' => $method->id,
                    'shipping_zone_id' => $zoneId,
                ]);
            }

            return $method->load('zones');
        });
    }
}
