<?php

namespace App\Domain\Analytics\Application;

use App\Domain\Analytics\Models\SavedReport;

final class CreateSavedReport
{
    /**
     * @param  array{name: string, report_type: string, filters?: array<string, mixed>, columns?: list<string>}  $data
     */
    public function handle(array $data): SavedReport
    {
        return SavedReport::query()->create([
            'name' => $data['name'],
            'report_type' => $data['report_type'],
            'filters' => $data['filters'] ?? [],
            'columns' => $data['columns'] ?? [],
        ]);
    }
}
