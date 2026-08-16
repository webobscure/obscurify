<?php

namespace App\Domain\RussianCommerce\Http\Resources;

use App\Domain\RussianCommerce\Models\StoreLegalProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StoreLegalProfile
 */
final class StoreLegalProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legal_entity_type' => $this->legal_entity_type->value,
            'legal_name' => $this->legal_name,
            'short_name' => $this->short_name,
            'inn' => $this->inn,
            'kpp' => $this->kpp,
            'ogrn' => $this->ogrn,
            'ogrnip' => $this->ogrnip,
            'legal_address' => $this->legal_address,
            'actual_address' => $this->actual_address,
            'email' => $this->email,
            'phone' => $this->phone,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
