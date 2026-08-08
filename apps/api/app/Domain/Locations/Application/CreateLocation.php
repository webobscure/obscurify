<?php

namespace App\Domain\Locations\Application;

use App\Domain\Locations\Enums\LocationStatus;
use App\Domain\Locations\Models\Location;

final class CreateLocation
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Location
    {
        $data['status'] ??= LocationStatus::Active->value;

        return Location::query()->create($data);
    }
}
