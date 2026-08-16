<?php

namespace App\Domain\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateMyLocaleRequest extends FormRequest
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
            'locale' => ['required', 'string', 'exists:locales,code'],
        ];
    }
}
