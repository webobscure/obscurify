<?php

namespace App\Domain\CustomerIntelligence\Application;

use App\Domain\CustomerIntelligence\Exceptions\SystemCustomerTagException;
use App\Domain\CustomerIntelligence\Models\CustomerTag;

final class DeleteCustomerTag
{
    public function handle(CustomerTag $tag): void
    {
        if ($tag->is_system) {
            throw SystemCustomerTagException::cannotDelete();
        }

        $tag->delete();
    }
}
