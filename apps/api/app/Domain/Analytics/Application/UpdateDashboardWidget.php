<?php

namespace App\Domain\Analytics\Application;

use App\Domain\Analytics\Models\DashboardWidget;

final class UpdateDashboardWidget
{
    /**
     * @param  array{type?: string, title?: string, config?: array<string, mixed>, position?: int}  $data
     */
    public function handle(DashboardWidget $widget, array $data): DashboardWidget
    {
        $widget->fill([
            'type' => $data['type'] ?? $widget->type,
            'title' => $data['title'] ?? $widget->title,
            'config' => $data['config'] ?? $widget->config,
            'position' => $data['position'] ?? $widget->position,
        ])->save();

        return $widget->fresh();
    }
}
