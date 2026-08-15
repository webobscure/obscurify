<?php

namespace App\Domain\Analytics\Http\Resources;

use App\Domain\Analytics\Models\SavedReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SavedReport
 */
final class SavedReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'report_type' => $this->report_type->value,
            'filters' => $this->filters,
            'columns' => $this->columns,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
