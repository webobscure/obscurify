<?php

namespace App\Domain\Analytics\Http\Resources;

use App\Domain\Analytics\Models\MetricDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MetricDefinition
 */
final class MetricDefinitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'category' => $this->category->value,
            'unit' => $this->unit->value,
            'calculation' => $this->calculation->value,
        ];
    }
}
