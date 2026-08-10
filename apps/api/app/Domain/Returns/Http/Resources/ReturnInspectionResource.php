<?php

namespace App\Domain\Returns\Http\Resources;

use App\Domain\Returns\Models\ReturnInspection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReturnInspection
 */
final class ReturnInspectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'condition' => $this->condition->value,
            'photos' => $this->photos,
            'notes' => $this->notes,
            'inspected_by' => $this->inspected_by,
            'inspected_at' => $this->inspected_at,
        ];
    }
}
