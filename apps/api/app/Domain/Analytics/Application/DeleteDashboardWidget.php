<?php

namespace App\Domain\Analytics\Application;

use App\Domain\Analytics\Models\DashboardWidget;

final class DeleteDashboardWidget
{
    public function handle(DashboardWidget $widget): void
    {
        $widget->delete();
    }
}
