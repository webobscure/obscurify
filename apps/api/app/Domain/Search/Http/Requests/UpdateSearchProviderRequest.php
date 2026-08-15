<?php

namespace App\Domain\Search\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSearchProviderRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'is_enabled' => ['sometimes', 'boolean'],
            'config' => ['sometimes', 'array'],
        ];
    }
}
