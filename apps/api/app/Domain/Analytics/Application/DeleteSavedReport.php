<?php

namespace App\Domain\Analytics\Application;

use App\Domain\Analytics\Models\SavedReport;

final class DeleteSavedReport
{
    public function handle(SavedReport $savedReport): void
    {
        $savedReport->delete();
    }
}
