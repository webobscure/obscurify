<?php

namespace App\Domain\RussianCommerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreFiscalizationProviderRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'is_enabled' => ['sometimes', 'boolean'],
            'config' => ['sometimes', 'array'],
            'credentials' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
