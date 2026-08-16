<?php

namespace App\Domain\RussianCommerce\Http\Requests;

use App\Domain\RussianCommerce\Enums\LegalEntityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateStoreLegalProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'legal_entity_type' => ['required', Rule::enum(LegalEntityType::class)],
            'legal_name' => ['required', 'string', 'max:255'],
            'short_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'inn' => ['required', 'string'],
            'kpp' => ['sometimes', 'nullable', 'string'],
            'ogrn' => ['sometimes', 'nullable', 'string'],
            'ogrnip' => ['sometimes', 'nullable', 'string'],
            'legal_address' => ['sometimes', 'nullable', 'array'],
            'actual_address' => ['sometimes', 'nullable', 'array'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
