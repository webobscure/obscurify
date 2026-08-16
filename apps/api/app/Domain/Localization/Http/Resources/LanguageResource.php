<?php

namespace App\Domain\Localization\Http\Resources;

use App\Domain\Localization\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Language
 */
final class LanguageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'native_name' => $this->native_name,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
