<?php

namespace App\Domain\Analytics\Application;

use App\Domain\Analytics\Models\Dashboard;

final class CreateDashboard
{
    /**
     * @param  array{name: string, is_default?: bool}  $data
     */
    public function handle(array $data): Dashboard
    {
        if ($data['is_default'] ?? false) {
            Dashboard::query()->update(['is_default' => false]);
        }

        return Dashboard::query()->create([
            'name' => $data['name'],
            'is_default' => $data['is_default'] ?? false,
        ]);
    }
}
