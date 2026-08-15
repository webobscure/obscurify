<?php

namespace App\Domain\Analytics\Http\Resources;

use App\Domain\Analytics\Models\DashboardWidget;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DashboardWidget
 */
final class DashboardWidgetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dashboard_id' => $this->dashboard_id,
            'type' => $this->type->value,
            'title' => $this->title,
            'config' => $this->config,
            'position' => $this->position,
        ];
    }
}
