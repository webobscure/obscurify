<?php

namespace App\Domain\Collections\Application;

use App\Domain\Collections\Models\Collection;

final class UpdateCollection
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Collection $collection, array $data): Collection
    {
        $collection->update($data);

        return $collection;
    }
}
