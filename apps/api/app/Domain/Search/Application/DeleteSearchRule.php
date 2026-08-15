<?php

namespace App\Domain\Search\Application;

use App\Domain\Search\Models\SearchRule;

final class DeleteSearchRule
{
    public function handle(SearchRule $rule): void
    {
        $rule->delete();
    }
}
