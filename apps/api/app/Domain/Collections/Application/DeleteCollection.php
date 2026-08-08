<?php

namespace App\Domain\Collections\Application;

use App\Domain\Collections\Models\Collection;

final class DeleteCollection
{
    public function handle(Collection $collection): void
    {
        $collection->delete();
    }
}
