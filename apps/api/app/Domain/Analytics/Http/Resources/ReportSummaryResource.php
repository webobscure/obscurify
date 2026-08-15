<?php

namespace App\Domain\Analytics\Http\Resources;

use App\Domain\Analytics\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The list-view shape — omits `result` (potentially up to
 * RunReport::MAX_ROWS rows) since a report list only needs to let the
 * merchant pick which one to open; see ReportResource for the full
 * detail shape.
 *
 * @mixin Report
 */
final class ReportSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'saved_report_id' => $this->saved_report_id,
            'report_type' => $this->report_type->value,
            'status' => $this->status->value,
            'row_count' => $this->row_count,
            'generated_at' => $this->generated_at,
            'created_at' => $this->created_at,
        ];
    }
}
