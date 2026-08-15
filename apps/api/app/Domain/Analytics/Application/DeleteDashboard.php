<?php

namespace App\Domain\Analytics\Application;

use App\Domain\Analytics\Models\Dashboard;

final class DeleteDashboard
{
    public function handle(Dashboard $dashboard): void
    {
        $dashboard->delete();
    }
}
