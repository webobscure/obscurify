<?php

namespace App\Domain\Analytics\Application;

use App\Domain\Analytics\Models\Dashboard;

final class UpdateDashboard
{
    /**
     * @param  array{name?: string, is_default?: bool}  $data
     */
    public function handle(Dashboard $dashboard, array $data): Dashboard
    {
        if (($data['is_default'] ?? false) === true) {
            Dashboard::query()->where('id', '!=', $dashboard->id)->update(['is_default' => false]);
        }

        $dashboard->fill([
            'name' => $data['name'] ?? $dashboard->name,
            'is_default' => $data['is_default'] ?? $dashboard->is_default,
        ])->save();

        return $dashboard->fresh();
    }
}
