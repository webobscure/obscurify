<?php

namespace App\Domain\Search\Application;

use App\Domain\Search\Models\SearchSynonym;

final class DeleteSearchSynonym
{
    public function handle(SearchSynonym $synonym): void
    {
        $synonym->delete();
    }
}
