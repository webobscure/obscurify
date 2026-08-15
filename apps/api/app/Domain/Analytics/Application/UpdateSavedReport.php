<?php

namespace App\Domain\Analytics\Application;

use App\Domain\Analytics\Models\SavedReport;

final class UpdateSavedReport
{
    /**
     * @param  array{name?: string, filters?: array<string, mixed>, columns?: list<string>}  $data
     */
    public function handle(SavedReport $savedReport, array $data): SavedReport
    {
        $savedReport->fill([
            'name' => $data['name'] ?? $savedReport->name,
            'filters' => $data['filters'] ?? $savedReport->filters,
            'columns' => $data['columns'] ?? $savedReport->columns,
        ])->save();

        return $savedReport->fresh();
    }
}
