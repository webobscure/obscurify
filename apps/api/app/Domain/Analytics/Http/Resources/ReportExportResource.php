<?php

namespace App\Domain\Analytics\Http\Resources;

use App\Domain\Analytics\Models\ReportExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReportExport
 */
final class ReportExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'report_id' => $this->report_id,
            'format' => $this->format->value,
            'status' => $this->status->value,
            'file_size' => $this->file_size,
            'scheduled_at' => $this->scheduled_at,
            'recurrence' => $this->recurrence?->value,
            'completed_at' => $this->completed_at,
            'download_url' => $this->status->value === 'completed' ? url("/api/v1/analytics/exports/{$this->id}/download") : null,
        ];
    }
}
