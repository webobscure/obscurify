<?php

namespace App\Domain\Locations\Application;

use App\Domain\Locations\Models\Location;

final class UpdateLocation
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Location $location, array $data): Location
    {
        $location->update($data);

        return $location;
    }
}
