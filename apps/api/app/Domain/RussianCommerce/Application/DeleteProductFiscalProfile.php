<?php

namespace App\Domain\RussianCommerce\Application;

use App\Domain\RussianCommerce\Models\ProductFiscalProfile;

final class DeleteProductFiscalProfile
{
    public function handle(ProductFiscalProfile $profile): void
    {
        $profile->delete();
    }
}
