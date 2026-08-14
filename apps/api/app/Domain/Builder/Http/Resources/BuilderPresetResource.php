<?php

namespace App\Domain\Builder\Http\Resources;

use App\Domain\Builder\Models\BuilderPreset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BuilderPreset
 */
final class BuilderPresetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'handle' => $this->handle,
            'name' => $this->name,
            'settings' => $this->settings,
        ];
    }
}
