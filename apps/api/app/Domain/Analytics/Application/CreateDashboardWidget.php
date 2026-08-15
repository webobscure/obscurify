<?php

namespace App\Domain\Analytics\Application;

use App\Domain\Analytics\Models\Dashboard;
use App\Domain\Analytics\Models\DashboardWidget;

final class CreateDashboardWidget
{
    /**
     * @param  array{type: string, title: string, config?: array<string, mixed>, position?: int}  $data
     */
    public function handle(Dashboard $dashboard, array $data): DashboardWidget
    {
        $position = $data['position'] ?? ((int) $dashboard->widgets()->max('position') + 1);

        return DashboardWidget::query()->create([
            'dashboard_id' => $dashboard->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'config' => $data['config'] ?? [],
            'position' => $position,
        ]);
    }
}
