<?php

namespace App\Domain\Search\Application;

use App\Domain\Search\Models\SearchProvider;

final class DeleteSearchProvider
{
    public function handle(SearchProvider $provider): void
    {
        $provider->delete();
    }
}
