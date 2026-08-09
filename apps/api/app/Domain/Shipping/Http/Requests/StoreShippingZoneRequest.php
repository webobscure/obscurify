<?php

namespace App\Domain\Shipping\Http\Requests;

use App\Domain\Shipping\Enums\ShippingZoneStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StoreShippingZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', new Enum(ShippingZoneStatus::class)],
            'regions' => ['sometimes', 'array'],
            'regions.*.country_code' => ['required_with:regions', 'string', 'size:2'],
            'regions.*.region' => ['sometimes', 'nullable', 'string', 'max:255'],
            'regions.*.postal_code_pattern' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
