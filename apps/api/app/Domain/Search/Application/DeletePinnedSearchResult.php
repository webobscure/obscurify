<?php

namespace App\Domain\Search\Application;

use App\Domain\Search\Models\PinnedSearchResult;

final class DeletePinnedSearchResult
{
    public function handle(PinnedSearchResult $pin): void
    {
        $pin->delete();
    }
}
