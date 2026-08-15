<?php

namespace App\Domain\Analytics\Http\Resources;

use App\Domain\Analytics\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Report
 */
final class ReportResource extends JsonResource
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
            'filters' => $this->filters,
            'columns' => $this->columns,
            'status' => $this->status->value,
            'result' => $this->result,
            'row_count' => $this->row_count,
            'error_message' => $this->error_message,
            'generated_at' => $this->generated_at,
            'created_at' => $this->created_at,
        ];
    }
}
